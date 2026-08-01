<?php

namespace App\Services\Matching;

use App\Models\Profile;
use App\Services\Matching\Scorers\AffinityScorer;
use App\Services\Matching\Scorers\ComponentScorer;
use App\Services\Matching\Scorers\LifestyleScorer;
use App\Services\Matching\Scorers\PersonalityScorer;
use App\Services\Matching\Scorers\PhysicalScorer;
use App\Services\Matching\Scorers\PreferencesScorer;
use App\Services\Matching\Scorers\ValuesScorer;

/**
 * Runs the six components and combines them into one number.
 *
 * Pure and deterministic end to end: the same two profiles always produce
 * the same score, so a member asking "why 87?" gets an answer that is still
 * true tomorrow, and the admin match log can show its working.
 *
 * ⚠️ The breakdown this returns is SERVER-SIDE ONLY. It is written to
 * matches.score_breakdown for the audit log and must never be serialised to
 * a member — it is the one place the internal mechanism is legible.
 */
class CompatibilityScorer
{
    /** @var list<ComponentScorer> */
    private array $scorers;

    public function __construct(
        AffinityScorer $affinity,
        PreferencesScorer $preferences,
        ValuesScorer $values,
        LifestyleScorer $lifestyle,
        PersonalityScorer $personality,
        PhysicalScorer $physical,
    ) {
        $this->scorers = [$affinity, $preferences, $values, $lifestyle, $personality, $physical];
    }

    /**
     * @return array{total: float, components: array<string, array{raw: float, weight: float, weighted: float}>, redistributed: array<string, float>, missing: list<string>}
     */
    public function score(Profile $seeker, Profile $candidate): array
    {
        $weights = config('matching.weights');

        $raw = [];
        $missing = [];

        foreach ($this->scorers as $scorer) {
            $value = $scorer->score($seeker, $candidate);

            if ($value === null) {
                $missing[] = $scorer->key();

                continue;
            }

            $raw[$scorer->key()] = max(0.0, min(100.0, $value));
        }

        if ($raw === []) {
            /*
             * Two profiles with nothing in common to compare. Not zero —
             * zero would mean "measured and incompatible" — but nothing that
             * could clear the threshold either. The hard filters should have
             * caught this long before scoring, so this is a floor, not a
             * path anyone should reach.
             */
            return [
                'total' => 0.0,
                'components' => [],
                'redistributed' => [],
                'missing' => $missing,
                'note' => 'No shared data to compare.',
            ];
        }

        /*
         * ── Weight redistribution ────────────────────────────────────────
         *
         * A component with no data does not score zero: that would punish an
         * incomplete profile twice, once for having less to match on and
         * again for being read as actively incompatible.
         *
         * Its weight is instead spread across the components that DID have
         * something to say, in proportion to their own weights, so the total
         * still means "out of 100". The redistribution is recorded so the
         * admin log can explain why a score with four components is
         * comparable to one with six.
         */
        $presentWeight = array_sum(array_intersect_key($weights, $raw));
        $factor = $presentWeight > 0 ? 100 / $presentWeight : 1;

        $components = [];
        $redistributed = [];
        $total = 0.0;

        foreach ($raw as $key => $value) {
            $effective = round($weights[$key] * $factor, 4);
            $weighted = round(($value * $effective) / 100, 4);

            $components[$key] = [
                'raw' => round($value, 2),
                'weight' => round($effective, 2),
                'weighted' => round($weighted, 2),
            ];

            if ($missing !== []) {
                $redistributed[$key] = round($effective - $weights[$key], 2);
            }

            $total += $weighted;
        }

        return [
            'total' => round(min(100.0, max(0.0, $total)), 2),
            'components' => $components,
            'redistributed' => $redistributed,
            'missing' => $missing,
        ];
    }
}
