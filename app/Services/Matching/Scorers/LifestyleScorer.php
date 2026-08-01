<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * How two people actually live day to day.
 *
 * Smoking and drinking use compatibility tables rather than equality. Two
 * heavy smokers are compatible with each other; a non-smoker and a heavy
 * smoker are not; a non-smoker and a social smoker are somewhere in between.
 * Equality gets the first case right and the third badly wrong.
 */
class LifestyleScorer implements ComponentScorer
{
    /**
     * Deliberately asymmetric where reality is. The row is the seeker's own
     * habit, the column the candidate's. A non-drinker paired with a regular
     * drinker (60) is a different proposition from a regular drinker paired
     * with a non-drinker (75) — the abstainer is the one who has to live
     * with it.
     */
    private const SMOKING = [
        'never' => ['never' => 100, 'socially' => 55, 'regularly' => 15],
        'socially' => ['never' => 80, 'socially' => 100, 'regularly' => 70],
        'regularly' => ['never' => 45, 'socially' => 80, 'regularly' => 100],
    ];

    private const DRINKING = [
        'never' => ['never' => 100, 'socially' => 65, 'regularly' => 25],
        'socially' => ['never' => 80, 'socially' => 100, 'regularly' => 70],
        'regularly' => ['never' => 45, 'socially' => 85, 'regularly' => 100],
    ];

    /** Broad bands. Precise institution matching is not this product's business. */
    private const EDUCATION_BANDS = [
        'school' => 0, 'secondary' => 0, 'high school' => 0,
        'diploma' => 1, 'college' => 1, 'associate' => 1,
        'bachelor' => 2, 'degree' => 2, 'undergraduate' => 2,
        'master' => 3, 'postgraduate' => 3, 'mba' => 3,
        'doctorate' => 4, 'phd' => 4,
    ];

    public function key(): string
    {
        return 'lifestyle';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        $parts = [];

        if ($seeker->smoking && $candidate->smoking) {
            $parts[] = (float) (self::SMOKING[$seeker->smoking][$candidate->smoking] ?? 50);
        }

        if ($seeker->drinking && $candidate->drinking) {
            $parts[] = (float) (self::DRINKING[$seeker->drinking][$candidate->drinking] ?? 50);
        }

        if ($education = $this->education($seeker->education, $candidate->education)) {
            $parts[] = $education;
        }

        if ($languages = $this->languages($seeker->languages ?? [], $candidate->languages ?? [])) {
            $parts[] = $languages;
        }

        if ($pets = $this->pets($seeker->pets ?? [], $candidate->pets ?? [])) {
            $parts[] = $pets;
        }

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }

    private function education(?string $a, ?string $b): ?float
    {
        $bandA = $this->band($a);
        $bandB = $this->band($b);

        if ($bandA === null || $bandB === null) {
            return null;
        }

        // One band apart is nothing. Four bands apart is worth noticing, but
        // it is still only one input among five in one component of six.
        return round(max(0, 100 - (abs($bandA - $bandB) * 18)), 2);
    }

    private function band(?string $education): ?int
    {
        if (! $education) {
            return null;
        }

        $needle = mb_strtolower($education);

        foreach (self::EDUCATION_BANDS as $term => $band) {
            if (str_contains($needle, $term)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Sharing no language at all is the single most practical obstacle two
     * people can have, so it scores low — but never zero, because the field
     * is free text and somebody typing "English" against "english " should
     * not be told they cannot speak to each other.
     */
    private function languages(array $a, array $b): ?float
    {
        $a = $this->normalise($a);
        $b = $this->normalise($b);

        if ($a === [] || $b === []) {
            return null;
        }

        $shared = count(array_intersect($a, $b));

        if ($shared === 0) {
            return 20.0;
        }

        return round(min(100, 70 + ($shared * 15)), 2);
    }

    private function pets(array $a, array $b): ?float
    {
        $a = $this->normalise($a);
        $b = $this->normalise($b);

        if ($a === [] || $b === []) {
            return null;
        }

        $aNone = in_array('none', $a, true);
        $bNone = in_array('none', $b, true);

        if ($aNone && $bNone) {
            return 100.0;
        }

        // One person's household has animals and the other's does not. Worth
        // something, not worth much.
        if ($aNone !== $bNone) {
            return 55.0;
        }

        return count(array_intersect($a, $b)) > 0 ? 100.0 : 70.0;
    }

    private function normalise(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : null, $values)
        )));
    }
}
