<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * What the two people said matters to them, and where they are going.
 *
 * The important detail is that core values are RANKED, and the ranking is
 * used. Two people who both put family first are not the same as two people
 * who both put it fifth — set intersection would score those identically and
 * would be wrong about which pair belongs together.
 */
class ValuesScorer implements ComponentScorer
{
    private const TIMELINE_ORDER = ['within_year', 'one_to_two', 'two_plus', 'no_fixed'];

    private const FAITH_ORDER = ['not_important', 'somewhat', 'important', 'essential'];

    public function key(): string
    {
        return 'values';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        $parts = [];

        if ($v = $this->coreValues($seeker->core_values ?? [], $candidate->core_values ?? [])) {
            // Weighted double: this is the heart of the component.
            $parts[] = $v;
            $parts[] = $v;
        }

        if ($seeker->marriage_timeline && $candidate->marriage_timeline) {
            $parts[] = $this->ordinalProximity(
                self::TIMELINE_ORDER, $seeker->marriage_timeline, $candidate->marriage_timeline
            );
        }

        if ($seeker->wants_children && $candidate->wants_children) {
            $parts[] = $this->children($seeker->wants_children, $candidate->wants_children);
        }

        if ($seeker->faith_importance && $candidate->faith_importance) {
            $parts[] = $this->faith($seeker, $candidate);
        }

        if ($seeker->conflict_style && $candidate->conflict_style) {
            $parts[] = $this->conflict($seeker->conflict_style, $candidate->conflict_style);
        }

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }

    /**
     * Rank-aware overlap.
     *
     * A shared value is worth more the higher BOTH people ranked it, and
     * worth less the further apart their rankings are. Scored against the
     * best achievable overlap for the shorter of the two lists, so somebody
     * who named three values is not penalised against somebody who named five.
     */
    private function coreValues(array $a, array $b): ?float
    {
        $a = array_values(array_filter($a));
        $b = array_values(array_filter($b));

        if ($a === [] || $b === []) {
            return null;
        }

        $earned = 0.0;

        foreach ($a as $i => $value) {
            $j = array_search($value, $b, true);

            if ($j === false) {
                continue;
            }

            // Rank 0 is worth 5 points, rank 4 worth 1. Agreement at the top
            // of both lists is the strongest signal in this component.
            $weight = (5 - min($i, 4)) + (5 - min($j, 4));

            // Halve it when they disagree sharply about how much it matters.
            $earned += abs($i - $j) >= 3 ? $weight / 2 : $weight;
        }

        $best = 0.0;
        for ($i = 0; $i < min(count($a), count($b)); $i++) {
            $best += (5 - min($i, 4)) * 2;
        }

        return $best > 0 ? round(min(100, ($earned / $best) * 100), 2) : null;
    }

    private function children(string $a, string $b): float
    {
        // Undecided is genuinely neutral: it is neither agreement nor conflict.
        if ($a === 'undecided' || $b === 'undecided') {
            return 55.0;
        }

        if ($a === $b) {
            return 100.0;
        }

        $pair = [$a, $b];
        sort($pair);

        return match ($pair) {
            ['open', 'yes'] => 85.0,
            ['no', 'open'] => 75.0,
            // The one real conflict in this field. Not eliminating — that is
            // the hard filter's job when it has been named a deal-breaker.
            ['no', 'yes'] => 5.0,
            default => 50.0,
        };
    }

    /**
     * Two people who both consider faith essential need to share one. Two who
     * both consider it unimportant do not — and scoring them as mismatched
     * because their nominal religions differ would be plainly wrong.
     */
    private function faith(Profile $seeker, Profile $candidate): float
    {
        $alignment = $this->ordinalProximity(
            self::FAITH_ORDER, $seeker->faith_importance, $candidate->faith_importance
        );

        $bothCare = in_array($seeker->faith_importance, ['important', 'essential'], true)
            && in_array($candidate->faith_importance, ['important', 'essential'], true);

        if (! $bothCare) {
            return $alignment;
        }

        $same = $seeker->religion && $candidate->religion
            && mb_strtolower(trim($seeker->religion)) === mb_strtolower(trim($candidate->religion));

        return $same ? 100.0 : 20.0;
    }

    private function conflict(string $a, string $b): float
    {
        if ($a === $b) {
            // Two avoiders agreeing to avoid is agreement, not health.
            return $a === 'avoid' ? 55.0 : 90.0;
        }

        $pair = [$a, $b];
        sort($pair);

        return match ($pair) {
            ['discuss_now', 'seek_compromise'] => 85.0,
            ['need_space', 'seek_compromise'] => 80.0,
            // The classic pursue/withdraw pattern.
            ['discuss_now', 'need_space'] => 35.0,
            ['avoid', 'discuss_now'] => 30.0,
            default => 55.0,
        };
    }

    /** Distance along an ordered scale, as a 0–100 closeness. */
    private function ordinalProximity(array $order, string $a, string $b): float
    {
        $i = array_search($a, $order, true);
        $j = array_search($b, $order, true);

        if ($i === false || $j === false) {
            return 50.0;
        }

        $span = count($order) - 1;

        return round(100 - (abs($i - $j) / $span) * 100, 2);
    }
}
