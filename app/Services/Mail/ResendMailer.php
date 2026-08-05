<?php

namespace App\Services\Mail;

use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sending email through Resend's API, and recording what was sent.
 *
 * ⚠️ THE API RATHER THAN SMTP, AND THE REASON IS THE LEDGER.
 *
 * SMTP works perfectly well for delivery — that is how the mail settings are
 * configured, and nothing about a message changes. What SMTP cannot give us is
 * RESEND'S OWN MESSAGE ID at the moment of sending.
 *
 * That id is the only thing the delivery webhook carries. Without it, a
 * `delivered` event arrives with nothing to attach it to, and every email in
 * the ledger stays at `accepted` forever — which is precisely the state we
 * just spent a day escaping. The admin panel would show "handed to the mail
 * server" for messages Resend had already confirmed delivered, and the
 * fallback chain would keep waiting on confirmations it could never match.
 *
 * The API returns the id synchronously. So: API for anything the ledger must
 * track, and the SMTP settings stay as the configured transport for anything
 * that does not — password resets, verification links, and Laravel's own
 * built-in mail.
 *
 * ⚠️ IT FALLS BACK TO SMTP RATHER THAN FAILING.
 *
 * If the API key is missing or the call throws, the message still goes out
 * over the configured transport and the ledger row is marked `accepted` with
 * no provider id. A member gets their receipt either way; we simply lose the
 * ability to confirm it arrived. Losing an email to protect a status column
 * would be the wrong trade.
 */
class ResendMailer
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(private readonly SettingsService $settings) {}

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * Send a mailable to a member and write the ledger row for it.
     *
     * @param  string  $template  What this message is, for the delivery log
     */
    public function send(User $recipient, Mailable $mailable, string $template, ?int $matchId = null): MessageDelivery
    {
        /*
         * ⚠️ THE ROW IS WRITTEN BEFORE THE SEND, NOT AFTER.
         *
         * Same reasoning as the WhatsApp dispatcher: a process that dies
         * mid-call leaves a visible row stuck at `queued`, which is
         * answerable. Written afterwards, the only record of a message that
         * may have reached somebody is gone.
         */
        $delivery = MessageDelivery::create([
            'user_id' => $recipient->id,
            'match_id' => $matchId,
            'channel' => MessageDelivery::CHANNEL_EMAIL,
            'template' => $template,
            'status' => MessageDelivery::STATUS_QUEUED,
            'attempts' => 1,
        ]);

        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return $this->viaSmtp($delivery, $recipient, $mailable, 'No Resend API key configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->asJson()
                ->post(self::ENDPOINT, [
                    'from' => $this->from(),
                    'to' => [$recipient->email],
                    'subject' => $mailable->envelope()->subject ?? 'RealVows',
                    'html' => $mailable->render(),
                    ...$this->textPart($mailable),
                ]);
        } catch (Throwable $e) {
            return $this->viaSmtp($delivery, $recipient, $mailable, $e->getMessage());
        }

        if (! $response->successful()) {
            return $this->viaSmtp($delivery, $recipient, $mailable, (string) $response->json('message', 'Resend refused the message.'));
        }

        /*
         * ⚠️ `accepted`, NOT `delivered`. Resend has taken the message; the
         * webhook says whether it arrived. Marking it delivered here would
         * reinstate exactly the lie the ceiling work removed.
         */
        $delivery->forceFill([
            'status' => MessageDelivery::STATUS_ACCEPTED,
            'provider_message_id' => $response->json('id'),
            'sent_at' => now(),
        ])->save();

        return $delivery;
    }

    /**
     * ⚠️ THE MESSAGE STILL GOES. Losing an email to protect a status column
     * would be the wrong trade — a member is owed their receipt whether or not
     * we can track it.
     */
    private function viaSmtp(MessageDelivery $delivery, User $recipient, Mailable $mailable, string $why): MessageDelivery
    {
        Log::channel('whatsapp-webhook')->warning('resend api unavailable, falling back to SMTP', [
            'delivery' => $delivery->id,
            'reason' => $why,
            'consequence' => 'The message is being sent, but with no provider id the delivery '
                .'webhook cannot match it, so this row will stay at accepted.',
        ]);

        try {
            Mail::to($recipient->email)->send($mailable);

            $delivery->forceFill([
                'status' => MessageDelivery::STATUS_ACCEPTED,
                'sent_at' => now(),
                'error' => 'Sent over SMTP; no provider id, so delivery cannot be confirmed.',
            ])->save();
        } catch (Throwable $e) {
            $delivery->forceFill([
                'status' => MessageDelivery::STATUS_FAILED,
                'retryable' => true,
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ])->save();
        }

        return $delivery;
    }

    /**
     * ⚠️ A TEXT PART IS NOT OPTIONAL. A multipart message without one scores
     * worse with spam filters, and on a domain with no reputation that is the
     * difference between the inbox and the junk folder.
     *
     * @return array<string, string>
     */
    private function textPart(Mailable $mailable): array
    {
        $content = $mailable->content();

        if (! $content->text) {
            return [];
        }

        try {
            return ['text' => view($content->text, $content->with)->render()];
        } catch (Throwable) {
            return [];
        }
    }

    private function from(): string
    {
        $address = $this->settings->get('mail', 'from_address') ?: 'introductions@realvows.skillleo.com';
        $name = $this->settings->get('mail', 'from_name') ?: 'RealVows';

        return "{$name} <{$address}>";
    }

    private function apiKey(): ?string
    {
        /*
         * ⚠️ THE DEDICATED FIELD FIRST, THE SMTP PASSWORD AS A FALLBACK.
         *
         * The API key had been living in the SMTP password field, because
         * Resend uses one credential for both. That worked and read as a
         * mistake — somebody looking at "SMTP password: ••••3jIF" has no way
         * to know it is an API key. It now has its own labelled field, and the
         * fallback stays so an installation configured the old way keeps
         * working rather than silently stopping.
         */
        foreach (['resend_api_key', 'password'] as $key) {
            $value = trim((string) $this->settings->get('mail', $key, ''));

            if (str_starts_with($value, 're_')) {
                return $value;
            }
        }

        return null;
    }
}
