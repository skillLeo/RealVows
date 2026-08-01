<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * Mutual physical satisfaction, scored in both directions and averaged.
 *
 * Both directions matter equally here, unlike PreferencesScorer where the
 * seeker's view leads. Attraction that runs one way is not attraction.
 *
 * Nobody is ever eliminated by this component — that only happens when a
 * member has explicitly marked body type as a deal-breaker, which the hard
 * filter handles before any scoring runs.
 */
class PhysicalScorer implements ComponentScorer
{
    public function key(): string
    {
        return 'physical';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        $forward = $this->satisfaction($seeker, $candidate);
        $back = $this->satisfaction($candidate, $seeker);

        $sides = array_filter([$forward, $back], fn ($v) => $v !== null);

        return $sides === [] ? null : round(array_sum($sides) / count($sides), 2);
    }

    private function satisfaction(Profile $for, Profile $of): ?float
    {
        $prefs = $for->preferences;

        if (! $prefs) {
            return null;
        }

        $parts = [];

        $wanted = $prefs->body_types ?? [];
        if ($wanted !== [] && $of->body_type) {
            // "Prefer not to say" is an absence of information, not a
            // mismatch, so it sits at neutral rather than being marked down.
            $parts[] = $of->body_type === 'prefer_not_to_say'
                ? 60.0
                : (in_array($of->body_type, $wanted, true) ? 100.0 : 35.0);
        }

        if ($of->height_cm && ($prefs->height_min_cm || $prefs->height_max_cm)) {
            $min = $prefs->height_min_cm ?? 120;
            $max = $prefs->height_max_cm ?? 230;

            if ($of->height_cm >= $min && $of->height_cm <= $max) {
                $parts[] = 100.0;
            } else {
                $off = $of->height_cm < $min ? $min - $of->height_cm : $of->height_cm - $max;

                // 10cm outside a stated range still scores 50. Height ranges
                // are usually a rough sketch rather than a specification.
                $parts[] = max(0.0, 100.0 - ($off * 5));
            }
        }

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }
}
