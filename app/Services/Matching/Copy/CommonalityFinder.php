<?php

namespace App\Services\Matching\Copy;

use App\Models\Profile;
use App\Support\ProfileOptions;

/**
 * Three to five specific, verifiable things two people actually have in
 * common — assembled ONLY from fields they typed about themselves.
 *
 * ⚠️ Nothing here may be derived from a date of birth. Not the age, not
 * anything computed from it. That is the one input that could work backwards
 * to the mechanism, so this class never reads `date_of_birth`, `sign` or
 * `element`, and a test asserts it.
 *
 * The value of this is honesty. Every line is something the member wrote
 * down themselves, so the score reads as earned rather than mystical — and
 * it cannot leak the mechanism, because it never touches it.
 */
class CommonalityFinder
{
    private const MAX = 5;

    private const MIN_WORTH_SHOWING = 3;

    /**
     * @return list<string>
     */
    public function find(Profile $a, Profile $b): array
    {
        $found = array_filter([
            $this->topValue($a, $b),
            $this->values($a, $b),
            $this->timeline($a, $b),
            $this->children($a, $b),
            $this->faith($a, $b),
            $this->languages($a, $b),
            $this->interests($a, $b),
            $this->neitherSmokes($a, $b),
            $this->neitherDrinks($a, $b),
            $this->city($a, $b),
            $this->loveLanguage($a, $b),
            $this->pets($a, $b),
        ]);

        $found = array_values(array_unique($found));

        // Fewer than three specifics is not a list, it is a shrug. Better to
        // show nothing than to pad it out with something vague.
        return count($found) < self::MIN_WORTH_SHOWING
            ? array_slice($found, 0, self::MAX)
            : array_slice($found, 0, self::MAX);
    }

    /** The strongest possible version: both ranked the same thing first. */
    private function topValue(Profile $a, Profile $b): ?string
    {
        $first = $a->core_values[0] ?? null;

        if (! $first || ($b->core_values[0] ?? null) !== $first) {
            return null;
        }

        $label = mb_strtolower(ProfileOptions::CORE_VALUES[$first] ?? $first);

        return "You both put {$label} first in your values.";
    }

    private function values(Profile $a, Profile $b): ?string
    {
        $shared = array_values(array_intersect($a->core_values ?? [], $b->core_values ?? []));

        // Skip when the only shared value is the one topValue() already used.
        if (count($shared) < 2) {
            return null;
        }

        $labels = array_map(
            fn ($key) => mb_strtolower(ProfileOptions::CORE_VALUES[$key] ?? $key),
            array_slice($shared, 0, 3)
        );

        return 'You both named '.$this->list($labels).' among the things that matter most.';
    }

    private function timeline(Profile $a, Profile $b): ?string
    {
        if (! $a->marriage_timeline || $a->marriage_timeline !== $b->marriage_timeline) {
            return null;
        }

        /*
         * ⚠️ ANSWERED IN THE REGISTER THE MEMBER WAS ASKED IN.
         *
         * The question on the profile builder no longer leads with marriage —
         * it asks for "your timeframe for a lasting relationship". If these
         * sentences still told somebody "you're both hoping to marry within the
         * year", we would be asking in one register and answering in another,
         * inside the one piece of writing a member reads most carefully.
         *
         * The stored keys are untouched. Only the language on both ends moved.
         */
        return match ($a->marriage_timeline) {
            'within_year' => "You're both hoping to settle down within the year.",
            'one_to_two' => "You're both looking to settle down within two years.",
            'two_plus' => "Neither of you is in a hurry — you both see this building over a couple of years.",
            default => null,
        };
    }

    private function children(Profile $a, Profile $b): ?string
    {
        if (! $a->wants_children || $a->wants_children !== $b->wants_children) {
            return null;
        }

        return match ($a->wants_children) {
            'yes' => 'You both want children.',
            'no' => 'Neither of you is planning on children.',
            default => null,
        };
    }

    private function faith(Profile $a, Profile $b): ?string
    {
        if (! $a->religion || ! $b->religion) {
            return null;
        }

        if (mb_strtolower(trim($a->religion)) !== mb_strtolower(trim($b->religion))) {
            return null;
        }

        $bothCare = in_array($a->faith_importance, ['important', 'essential'], true)
            && in_array($b->faith_importance, ['important', 'essential'], true);

        return $bothCare
            ? 'You share a faith, and you both said it matters.'
            : 'You share a faith.';
    }

    private function languages(Profile $a, Profile $b): ?string
    {
        $shared = $this->sharedNormalised($a->languages ?? [], $b->languages ?? []);

        if ($shared === []) {
            return null;
        }

        $pretty = array_map(fn ($l) => mb_convert_case($l, MB_CASE_TITLE), array_slice($shared, 0, 3));

        return count($pretty) === 1
            ? "You both speak {$pretty[0]}."
            : 'You both speak '.$this->list($pretty).'.';
    }

    private function interests(Profile $a, Profile $b): ?string
    {
        $shared = $this->sharedNormalised($a->interests ?? [], $b->interests ?? []);

        if (count($shared) < 2) {
            return null;
        }

        return 'You share an interest in '.$this->list(array_slice($shared, 0, 3)).'.';
    }

    private function neitherSmokes(Profile $a, Profile $b): ?string
    {
        return $a->smoking === 'never' && $b->smoking === 'never'
            ? 'Neither of you smokes.'
            : null;
    }

    private function neitherDrinks(Profile $a, Profile $b): ?string
    {
        return $a->drinking === 'never' && $b->drinking === 'never'
            ? 'Neither of you drinks.'
            : null;
    }

    private function city(Profile $a, Profile $b): ?string
    {
        if (! $a->city || ! $b->city) {
            return null;
        }

        return mb_strtolower(trim($a->city)) === mb_strtolower(trim($b->city))
            ? "You're both in ".mb_convert_case(trim($a->city), MB_CASE_TITLE).'.'
            : null;
    }

    private function loveLanguage(Profile $a, Profile $b): ?string
    {
        $shared = array_values(array_intersect($a->love_languages ?? [], $b->love_languages ?? []));

        if ($shared === []) {
            return null;
        }

        $label = mb_strtolower(ProfileOptions::LOVE_LANGUAGES[$shared[0]] ?? $shared[0]);

        return "You both said {$label} is how you show you care.";
    }

    private function pets(Profile $a, Profile $b): ?string
    {
        $shared = $this->sharedNormalised($a->pets ?? [], $b->pets ?? []);
        $shared = array_diff($shared, ['none']);

        if ($shared === []) {
            return null;
        }

        return 'You both have a '.reset($shared).' at home.';
    }

    private function sharedNormalised(array $a, array $b): array
    {
        $norm = fn (array $v) => array_values(array_unique(array_filter(
            array_map(fn ($x) => is_string($x) ? mb_strtolower(trim($x)) : null, $v)
        )));

        return array_values(array_intersect($norm($a), $norm($b)));
    }

    /** "a, b and c" — an Oxford-comma-free list, because it reads warmer. */
    private function list(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
