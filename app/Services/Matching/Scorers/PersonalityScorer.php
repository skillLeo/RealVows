<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * Shared interests, and how the two people are wired.
 *
 * ⚠️ Proximity is NOT the goal on every scale, and encoding it globally is
 * the mistake this class exists to avoid.
 *
 * Two people who both answered "extremely spontaneous" have nobody booking
 * the flights. Some difference on planning is healthy; total difference is
 * not. The other three scales genuinely do reward similarity — two people
 * who disagree completely about whether decisions are made with the head or
 * the heart will disagree about every decision they ever make together.
 */
class PersonalityScorer implements ComponentScorer
{
    /**
     * 'similar'      — closer is better, all the way.
     * 'complementary'— an ideal gap, with falloff either side of it.
     */
    private const SCALE_RULES = [
        'social' => ['mode' => 'similar'],
        'planning' => ['mode' => 'complementary', 'ideal_gap' => 30],
        'decisions' => ['mode' => 'similar'],
        'outlook' => ['mode' => 'similar'],
    ];

    public function key(): string
    {
        return 'personality';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        $parts = [];

        if ($interests = $this->interests($seeker->interests ?? [], $candidate->interests ?? [])) {
            $parts[] = $interests;
        }

        if ($scales = $this->scales($seeker->personality_scales ?? [], $candidate->personality_scales ?? [])) {
            // Weighted double: how somebody is wired outlasts what they are
            // currently interested in.
            $parts[] = $scales;
            $parts[] = $scales;
        }

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }

    /**
     * Jaccard overlap, rescaled.
     *
     * Raw Jaccard is punishing here: two people sharing three interests out
     * of twelve each score 0.14, which reads as incompatible when it is
     * actually three things to talk about on a first meeting. The curve
     * treats any genuine overlap as a positive signal.
     */
    private function interests(array $a, array $b): ?float
    {
        $a = $this->normalise($a);
        $b = $this->normalise($b);

        if ($a === [] || $b === []) {
            return null;
        }

        $shared = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        if ($union === 0) {
            return null;
        }

        $jaccard = $shared / $union;

        return round(min(100, 35 + ($jaccard * 160)), 2);
    }

    private function scales(array $a, array $b): ?float
    {
        $scores = [];

        foreach (self::SCALE_RULES as $scale => $rule) {
            $x = $a[$scale] ?? null;
            $y = $b[$scale] ?? null;

            if (! is_numeric($x) || ! is_numeric($y)) {
                continue;
            }

            $gap = abs((int) $x - (int) $y);

            $scores[] = $rule['mode'] === 'similar'
                ? 100 - $gap                     // 0 apart = 100, 100 apart = 0
                : max(0, 100 - abs($gap - $rule['ideal_gap']) * 1.4);
        }

        return $scores === [] ? null : round(array_sum($scores) / count($scores), 2);
    }

    private function normalise(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : null, $values)
        )));
    }
}
