<?php

namespace App\Services\Ai;

/**
 * Does the rewrite say anything the member did not?
 *
 * ══════════════════════════════════════════════════════════════════════════
 * ⚠️ A PROFILE THAT STATES UNTRUE THINGS IS DECEPTION
 * ══════════════════════════════════════════════════════════════════════════
 *
 * This product just spent a week removing a matching mechanism because it could
 * not be explained honestly to the person it was applied to. Inventing a job, a
 * hobby or a hometown for somebody's profile is the same category of harm and a
 * more direct one: another member reads it, believes it, and decides whether to
 * meet a stranger partly on the strength of it.
 *
 * ⚠️ THE CHECK RUNS ON THE OUTPUT, NOT ON THE PROMPT.
 *
 * "The system prompt tells it not to invent things" is not a control. It is a
 * request. Models comply with it most of the time, which is the worst possible
 * failure rate: frequent enough to look reliable, rare enough that nobody is
 * watching when it does not.
 *
 * So every proper noun and every number in the rewrite must appear in the input.
 * A rewrite that fails is discarded and the member sees their own words.
 */
class ProfileWriterFidelity
{
    /**
     * Words that begin a sentence and are capitalised for that reason alone,
     * plus the handful a rewrite may legitimately introduce.
     *
     * ⚠️ THIS LIST IS THE ONLY PLACE THE CHECK CAN BE LOOSENED, and every
     * addition widens what a model is allowed to invent. It is short on purpose.
     */
    private const IGNORED = [
        'A', 'An', 'The', 'I', 'My', 'We', 'It', 'He', 'She', 'They', 'This', 'That',
        'And', 'But', 'For', 'In', 'On', 'At', 'To', 'Of', 'With', 'When', 'Where',
        'What', 'While', 'After', 'Before', 'Both', 'There', 'These', 'Those',
        'If', 'As', 'So', 'Then', 'Now', 'Also', 'Or', 'Not', 'No', 'Yes',
    ];

    /**
     * Every claim in the rewrite that the input does not support.
     *
     * @return list<string>
     */
    public function unsupportedClaims(string $original, string $rewrite): array
    {
        $haystack = $this->normalise($original);
        $offences = [];

        // Proper nouns: capitalised words that are not sentence openers.
        preg_match_all('/\b[A-Z][a-zA-Z\'’-]{1,}\b/', $rewrite, $matches);

        foreach (array_unique($matches[0]) as $noun) {
            if (in_array($noun, self::IGNORED, true)) {
                continue;
            }

            if (! str_contains($haystack, $this->normalise($noun))) {
                $offences[] = $noun;
            }
        }

        /*
         * ⚠️ NUMBERS TOO. "Two children", "fifteen years", "three languages" —
         * a model that rounds or embellishes a quantity has changed a fact
         * about somebody's life, and a reader has no way to know.
         */
        preg_match_all('/\b\d+\b/', $rewrite, $numbers);

        foreach (array_unique($numbers[0]) as $number) {
            if (! str_contains($haystack, $number)) {
                $offences[] = $number;
            }
        }

        return array_values(array_unique($offences));
    }

    public function isFaithful(string $original, string $rewrite): bool
    {
        return $this->unsupportedClaims($original, $rewrite) === [];
    }

    private function normalise(string $text): string
    {
        return strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $text) ?? $text);
    }
}
