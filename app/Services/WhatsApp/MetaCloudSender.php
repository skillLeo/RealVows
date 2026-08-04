<?php

namespace App\Services\WhatsApp;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta's WhatsApp Cloud API.
 *
 * ⚠️ EVERY CREDENTIAL IS READ FROM SETTINGS AT CALL TIME, NEVER CACHED IN A
 * PROPERTY. The client rotates the access token in his browser; a value read
 * once into a singleton would keep using the old one until the process
 * restarted, which on shared hosting could be a very long time.
 */
class MetaCloudSender implements WhatsAppSender
{
    /*
     * ⚠️ PINNED. Meta deprecates a version roughly every three months and an
     * unpinned URL changes behaviour under us with no deploy. The webhook
     * fields in the dashboard must be on this same version — mixing them is
     * what produces "some updates arrive late" reports that look like our bug.
     */
    private const GRAPH_VERSION = 'v23.0';

    public function __construct(private readonly SettingsService $settings) {}

    public function sendTemplate(string $to, string $template, array $variables): WhatsAppResult
    {
        $phoneNumberId = $this->setting('phone_number_id');
        $token = $this->setting('access_token');

        if (! $phoneNumberId || ! $token) {
            return WhatsAppResult::permanentFailure('WhatsApp is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asJson()
                ->post(sprintf('https://graph.facebook.com/%s/%s/messages', self::GRAPH_VERSION, $phoneNumberId), [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $this->setting('template_language') ?: 'en'],
                        'components' => $variables === [] ? [] : [[
                            'type' => 'body',
                            'parameters' => array_map(
                                fn (string $value) => ['type' => 'text', 'text' => $value],
                                array_values($variables),
                            ),
                        ]],
                    ],
                ]);
        } catch (Throwable $e) {
            // A connection that never completed. Always worth another go.
            return WhatsAppResult::temporaryFailure($e->getMessage());
        }

        if ($response->successful()) {
            return WhatsAppResult::sent(
                $response->json('messages.0.id') ?? 'unknown'
            );
        }

        return $this->classify($response->status(), (string) $response->json('error.message', 'Unknown error'), $response->json('error.code'));
    }

    public function mode(): string
    {
        return 'live';
    }

    /**
     * ⚠️ WHICH FAILURES ARE WORTH REPEATING.
     *
     * Retrying a permanent failure is not merely wasteful: every attempt is
     * billed, and repeated failures to the same number push down the quality
     * rating Meta uses to decide our sending limits. Three retries against a
     * mistyped number is how a working account becomes a throttled one.
     */
    private function classify(int $status, string $message, ?int $code): WhatsAppResult
    {
        // 429 and 5xx are Meta's problem and will pass.
        if ($status === 429 || $status >= 500) {
            return WhatsAppResult::temporaryFailure($message, $code);
        }

        /*
         * 131026 — the number is not on WhatsApp, or cannot receive.
         * 132xxx — template missing, unapproved, or wrong parameter count.
         * 190    — the access token expired or was revoked.
         *
         * None of these change by trying again. The last one needs a human: the
         * client must generate a new System User token.
         */
        return WhatsAppResult::permanentFailure($message, $code);
    }

    private function setting(string $key): ?string
    {
        $value = trim((string) $this->settings->get('whatsapp', $key, ''));

        return $value === '' ? null : $value;
    }
}
