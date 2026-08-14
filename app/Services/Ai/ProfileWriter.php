<?php

namespace App\Services\Ai;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rewrites a member's own words. Adds nothing.
 *
 * ⚠️ THE FIDELITY CHECK IS A GATE, NOT A WARNING.
 *
 * A rewrite that introduces a fact the member did not supply is DISCARDED here.
 * It is never shown, never offered, never stored. Showing it with a caution
 * attached would put the burden on a member to notice that a machine invented
 * their employer, and somebody halfway through signing up will click accept.
 *
 * ⚠️ THE MEMBER'S ORIGINAL IS ALWAYS THE FALLBACK. Every failure path — no key,
 * provider down, unfaithful output, empty response — returns null, and the
 * caller keeps what the member typed. Their own words are never worse than
 * nothing.
 */
class ProfileWriter
{
    /** ⚠️ Five per member per day. A rewrite is a considered act, not a slot machine. */
    public const DAILY_LIMIT = 5;

    /**
     * ⚠️ A LITERAL, BECAUSE THE EXPRESSIVE VERSION WAS NEGATIVE.
     *
     * `now()->addDay()->diffInSeconds(now())` reads as one day and evaluates to
     * minus 86,400. A negative decay expires the counter immediately, which
     * meant the limit never limited anything.
     */
    public const LIMIT_WINDOW_SECONDS = 86400;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ProfileWriterFidelity $fidelity,
    ) {}

    public function isEnabled(): bool
    {
        try {
            return $this->apiKey() !== null
                && $this->settings->bool('ai', 'writer_enabled', true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{rewrite: string, model: string}|null Null means keep the original.
     */
    public function rewrite(string $original): ?array
    {
        $original = trim($original);

        if ($original === '' || ! $this->isEnabled()) {
            return null;
        }

        $model = (string) ($this->settings->get('ai', 'writer_model') ?: 'gpt-4o-mini');

        try {
            $response = Http::withToken($this->apiKey())
                ->timeout(20)
                ->asJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => $original],
                    ],
                ]);

            if (! $response->successful()) {
                return $this->abandon('provider returned '.$response->status(), $original);
            }

            $decoded = json_decode((string) $response->json('choices.0.message.content'), true);
            $rewrite = trim((string) ($decoded['rewrite'] ?? ''));

            if ($rewrite === '') {
                return $this->abandon('empty rewrite', $original);
            }

            /*
             * ⚠️ THE GATE. Not a warning, not a flag on the screen — the
             * unfaithful rewrite does not leave this method.
             */
            $unsupported = $this->fidelity->unsupportedClaims($original, $rewrite);

            if ($unsupported !== []) {
                return $this->abandon(
                    'invented: '.implode(', ', $unsupported),
                    $original,
                );
            }

            /*
             * ⚠️ A REWRITE THAT IS SHORTER THAN THE ORIGINAL IS NOT A TIDY-UP.
             *
             * The bio has a 150-word floor, so a member who is already short and
             * presses the button expects to move toward it. A model that
             * "tidies" by cutting leaves them further from finishing than
             * before, having spent one of their five daily attempts to get
             * worse. The instruction says never return fewer words; this is what
             * makes it true.
             *
             * Discarded rather than shown with a caution, for the same reason
             * the fidelity gate discards: a member halfway through signing up
             * accepts whatever is in front of them.
             */
            if (str_word_count($rewrite) < str_word_count($original)) {
                return $this->abandon(
                    'rewrite was shorter than the original ('
                        .str_word_count($rewrite).' vs '.str_word_count($original).' words)',
                    $original,
                );
            }

            return ['rewrite' => $rewrite, 'model' => $model];
        } catch (Throwable $e) {
            return $this->abandon($e->getMessage(), $original);
        }
    }

    /**
     * ⚠️ THE INSTRUCTIONS ARE NOT THE CONTROL. They make a faithful rewrite
     * more likely; ProfileWriterFidelity is what makes an unfaithful one
     * impossible to ship. Both are needed and only one is a guarantee.
     */
    private function instructions(): string
    {
        return <<<'PROMPT'
        You tidy the English of a dating profile written by its own author.

        Reply with JSON: {"rewrite": "..."}

        Rules:
        - Use ONLY facts present in the input. Never add a job, a place, a
          hobby, a family detail, a number, or any physical description.
        - If the input is vague, leave it vague. Do not fill gaps.
        - Keep the author's voice. Fix grammar, spelling and word order.
        - Warm and dignified. Never romantic, flirtatious or suggestive.
        - British English. No em dashes, no semicolons, no exclamation marks.
        - The profile needs at least 150 words. Work every point the author
          made into a full, natural sentence and let their own material breathe.
        - NEVER invent material to reach that length. If what they wrote cannot
          honestly support 150 words, return what you have. A short honest
          profile is better than a padded one, and the member will be told they
          need to write more.
        - Never return fewer words than you were given.
        PROMPT;
    }

    private function abandon(string $why, string $original): null
    {
        Log::info('profile rewrite discarded, member keeps their own words', [
            'reason' => $why,
            'original_length' => strlen($original),
            'consequence' => 'The member sees no suggestion. Nothing is shown to them and '
                .'nothing is stored.',
        ]);

        return null;
    }

    private function apiKey(): ?string
    {
        $key = trim((string) $this->settings->get('ai', 'openai_api_key', ''));

        return $key === '' ? null : $key;
    }
}
