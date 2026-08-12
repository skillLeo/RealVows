<?php

namespace App\Services\Matching;

use App\Models\Profile;
use App\Services\Matching\Scorers\ComponentScorer;
use App\Services\Matching\Scorers\LifestyleScorer;
use App\Services\Matching\Scorers\PersonalityScorer;
use App\Services\Matching\Scorers\PhysicalScorer;
use App\Services\Matching\Scorers\PreferencesScorer;
use App\Services\Matching\Scorers\ValuesScorer;

/**
 * Runs the five components and combines them into one number.
 *
 * Pure and deterministic end to end: the same two profiles always produce
 * the same score, so a member asking "why 87?" gets an answer that is still
 * true tomorrow, and the admin match log can show its working.
 *
 * ⚠️ The breakdown this returns is SERVER-SIDE ONLY. It is written to
 * matches.score_breakdown for the audit log.
 *
 * That used to be a secrecy requirement, because one of the six components was
 * an affinity score derived from date of birth, and the breakdown was the
 * one place that mechanism was legible. That component is gone. The breakdown stays
 * server-side now for a duller and better reason: a member does not need five
 * numbers to understand why somebody was chosen, and showing them invites an
 * argument about the arithmetic rather than a conversation about the person.
 */
class CompatibilityScorer
{
    /** @var list<ComponentScorer> */
    private array $scorers;

    public function __construct(
        PreferencesScorer $preferences,
        ValuesScorer $values,
        LifestyleScorer $lifestyle,
        PersonalityScorer $personality,
        PhysicalScorer $physical,
    ) {
        $this->scorers = [$preferences, $values, $lifestyle, $personality, $physical];
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

        /*
         * ── ⚠️ THE ROUNDING REMAINDER GOES SOMEWHERE ON PURPOSE ───────────
         *
         * `weight` is rounded to 2dp for the audit log. Redistributing a
         * missing component's weight across the rest produces repeating
         * decimals, and five numbers each rounded to 2dp do not have to sum to
         * 100. With the previous six weights they summed to 100.01 or 99.98 in
         * five of the six possible cases.
         *
         * That drift shipped for months and the test meant to catch it passed,
         * because its fixture happened to omit the ONE component whose removal
         * landed exactly on 100. A test that holds only for the case somebody
         * happened to write is the shape this project keeps finding.
         *
         * The remainder is given to the largest present component, which is the
         * one where a hundredth of a point is least visible. The stored weights
         * now sum to exactly 100 for every possible omission, which is what the
         * admin log claims when it says "out of 100".
         *
         * ⚠️ THE TOTAL IS NOT ADJUSTED, only the displayed weights. `$total` is
         * accumulated from the unrounded values and is already correct; nudging
         * it to match a rounding artefact would corrupt the actual score to
         * tidy up its own description.
         */
        if ($components !== []) {
            $sum = round(array_sum(array_column($components, 'weight')), 2);
            $remainder = round(100 - $sum, 2);

            if (abs($remainder) >= 0.01) {
                $largest = array_key_first($components);

                foreach ($components as $key => $c) {
                    if ($c['weight'] > $components[$largest]['weight']) {
                        $largest = $key;
                    }
                }

                $components[$largest]['weight'] = round($components[$largest]['weight'] + $remainder, 2);
            }
        }

        return [
            'total' => round(min(100.0, max(0.0, $total)), 2),
            'components' => $components,
            'redistributed' => $redistributed,
            'missing' => $missing,
        ];
    }
}
