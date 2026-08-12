<?php

namespace App\Services\Risk;

use App\Models\Guardian;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only part of risk scoring a model touches.
 *
 * ⚠️ IT CONTRIBUTES NO NUMBER. It returns prose. Every point in the score is
 * decided by a rule in GuardianRiskScorer, because every signal there is a fact
 * a database can check, and asking a probabilistic system a deterministic
 * question buys nothing but variance.
 *
 * ⚠️ NO PHOTOGRAPH IS EVER SENT. The joint photograph is the most sensitive
 * artefact in the product and a human of the appropriate gender reviews it. The
 * model receives a name, a relationship, and the flags the rules already
 * raised — nothing that identifies anybody beyond a first name.
 *
 * ⚠️ AN OUTAGE MUST NEVER BLOCK A MEMBER. Every failure path returns null, the
 * scorer records `modelUnavailable`, and the admin sees rules-only scoring with
 * a note saying so. Verification continues. A member waiting on a queue because
 * a third-party API is down is a worse failure than a missing sentence.
 */
class RiskNarrator
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * ⚠️ "NOT CONFIGURED" AND "UNAVAILABLE" ARE DIFFERENT ANSWERS.
     *
     * No API key means the feature is off and the product is expected to work
     * without it — nothing to report to an admin. A configured key that fails
     * means a summary is MISSING that should have been there, and the admin
     * needs to know they are reading a partial picture. Collapsing the two
     * would either cry wolf on every install or hide a real outage.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @param  list<string>  $reasons
     * @return array{summary: string, model: string}|null
     */
    public function describe(Guardian $guardian, array $reasons): ?array
    {
        $key = $this->apiKey();

        if ($key === null) {
            // Not an error. No key configured means the feature is off, and
            // the product is expected to work without it.
            return null;
        }

        $model = (string) ($this->settings->get('ai', 'risk_model') ?: 'gpt-4o-mini');

        try {
            $response = Http::withToken($key)
                ->timeout(12)
                ->asJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You help a human reviewer. You never decide anything. '
                                .'Reply with JSON: {"summary": "two sentences at most"}. '
                                .'Say only what the input supports. Do not speculate about '
                                .'anybody\'s character or intentions.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'declared_relationship' => $guardian->relationship,
                                'contact_first_name' => explode(' ', trim($guardian->name))[0],
                                'automated_flags' => $reasons,
                            ]),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                $this->logUnavailable('non-2xx from provider', $response->status());

                return null;
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode((string) $content, true);

            if (! is_array($decoded) || ! isset($decoded['summary'])) {
                $this->logUnavailable('unparseable response', null);

                return null;
            }

            return ['summary' => (string) $decoded['summary'], 'model' => $model];
        } catch (Throwable $e) {
            $this->logUnavailable($e->getMessage(), null);

            return null;
        }
    }

    private function apiKey(): ?string
    {
        try {
            $key = trim((string) $this->settings->get('ai', 'openai_api_key', ''));
        } catch (Throwable) {
            return null;
        }

        return $key === '' ? null : $key;
    }

    private function logUnavailable(string $why, ?int $status): void
    {
        Log::info('risk narration unavailable, continuing on rules only', [
            'reason' => $why,
            'status' => $status,
            'consequence' => 'The admin sees the rules-based score and reasons, with a note that '
                .'the written summary could not be produced. Verification is not blocked.',
        ]);
    }
}
