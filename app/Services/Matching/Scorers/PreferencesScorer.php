<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * How well the candidate satisfies what the seeker asked for — multiplied by
 * whether the seeker also satisfies what the CANDIDATE asked for.
 *
 * The reciprocity factor is the point. A one-sided match is worth less than
 * a mutual one, because an introduction where only one person is interested
 * wastes both people's time. Satisfying the seeker perfectly while failing
 * the candidate entirely lands at 60% of the raw score, not 100%.
 */
class PreferencesScorer implements ComponentScorer
{
    /** How much of the score survives when the other side is not satisfied at all. */
    private const RECIPROCITY_FLOOR = 0.6;

    public function key(): string
    {
        return 'preferences';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        $forward = $this->satisfaction($seeker, $candidate);

        if ($forward === null) {
            return null;
        }

        $back = $this->satisfaction($candidate, $seeker);

        // Nothing stated by the candidate means nothing to fail. An unstated
        // preference is not a rejection.
        $reciprocity = $back === null
            ? 1.0
            : self::RECIPROCITY_FLOOR + ((1 - self::RECIPROCITY_FLOOR) * ($back / 100));

        return round($forward * $reciprocity, 2);
    }

    /**
     * How well `$of` meets what `$for` asked for, 0–100.
     *
     * @return float|null Null when `$for` stated no preferences at all.
     */
    private function satisfaction(Profile $for, Profile $of): ?float
    {
        $prefs = $for->preferences;

        if (! $prefs) {
            return null;
        }

        $parts = [];

        // ── Age ──────────────────────────────────────────────────────────
        $age = $of->age();
        if ($age !== null && ($prefs->age_min || $prefs->age_max)) {
            $min = $prefs->age_min ?? 18;
            $max = $prefs->age_max ?? 120;

            $parts[] = $this->proximity($age, $min, $max, tolerance: 5);
        }

        // ── Height ───────────────────────────────────────────────────────
        if ($of->height_cm && ($prefs->height_min_cm || $prefs->height_max_cm)) {
            $min = $prefs->height_min_cm ?? 120;
            $max = $prefs->height_max_cm ?? 230;

            $parts[] = $this->proximity($of->height_cm, $min, $max, tolerance: 10);
        }

        // ── Body type ────────────────────────────────────────────────────
        // An empty selection means "not narrowing this", which is different
        // from "none of these" — so it contributes nothing rather than zero.
        $wanted = $prefs->body_types ?? [];
        if ($wanted !== [] && $of->body_type) {
            $parts[] = in_array($of->body_type, $wanted, true) ? 100.0 : 25.0;
        }

        // ── Distance ─────────────────────────────────────────────────────
        if ($prefs->distance_preference) {
            $parts[] = $this->distance($for, $of, $prefs->distance_preference);
        }

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }

    /**
     * Inside the range scores full. Outside decays linearly over `tolerance`
     * rather than falling off a cliff — someone one year outside a stated
     * range is not the same as someone twenty years outside it, and treating
     * them identically is how a good match gets thrown away over a rounding.
     */
    private function proximity(int $value, int $min, int $max, int $tolerance): float
    {
        if ($value >= $min && $value <= $max) {
            return 100.0;
        }

        $distance = $value < $min ? $min - $value : $value - $max;

        return max(0.0, 100.0 - (($distance / $tolerance) * 100));
    }

    private function distance(Profile $for, Profile $of, string $preference): float
    {
        // Willingness to relocate answers the question before it is asked.
        if ($of->willing_to_relocate) {
            return 100.0;
        }

        $sameCity = $for->city && $of->city
            && mb_strtolower(trim($for->city)) === mb_strtolower(trim($of->city));

        $sameCountry = $for->country && $of->country
            && mb_strtolower(trim($for->country)) === mb_strtolower(trim($of->country));

        return match ($preference) {
            'same_city' => $sameCity ? 100.0 : ($sameCountry ? 45.0 : 10.0),
            'same_country' => $sameCountry ? 100.0 : 30.0,
            default => 100.0, // 'anywhere'
        };
    }
}
