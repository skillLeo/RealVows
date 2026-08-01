<?php

namespace App\Services\Matching;

/**
 * ⚠️ INTERNAL ONLY. NOTHING THIS CLASS TOUCHES MAY BE SERIALISED.
 *
 * The affinity component of the compatibility score. It reads the two values
 * SignCalculator derives from a date of birth and returns a number.
 *
 * This file, and this file alone, is permitted to name the twelve periods —
 * it is excluded from the CI content check because nothing here reaches a
 * browser, an email, a JSON response or a template. If you find yourself
 * wanting to pass a key from this class into a view, stop: the whole
 * commercial premise is that members never learn the mechanism. They see a
 * score and a rationale written from what they told us about themselves.
 *
 * ── How the numbers are derived ──────────────────────────────────────────
 *
 * Purely from the angular relationship between two periods, which is what
 * element and modality harmony reduces to. With the twelve in their natural
 * order, the separation d = min(|i−j|, 12−|i−j|) gives:
 *
 *   d=0  same period        75   familiar, occasionally too alike
 *   d=1  adjacent           55   little in common, neither drawn nor repelled
 *   d=2  complementary      85   different element, historically easy pairing
 *   d=3  tension            40   same modality, clashing element
 *   d=4  same element       92   the strongest pairing
 *   d=5  awkward            45   almost nothing shared
 *   d=6  opposite           70   real attraction, real friction
 *
 * That makes the matrix circulant — every row is the previous row rotated by
 * one — and symmetric, which is asserted in the tests. It is written out in
 * full rather than computed so that it is inspectable, constant-time, and
 * cannot drift if someone edits the derivation.
 */
class AffinityMatrix
{
    /**
     * Natural order. The index of a key here is its position in the matrix.
     *
     * @var list<string>
     */
    public const ORDER = [
        'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
        'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces',
    ];

    /**
     * 12×12, symmetric, values 40–92.
     *
     * @var list<list<int>>
     */
    private const MATRIX = [
        [75, 55, 85, 40, 92, 45, 70, 45, 92, 40, 85, 55],
        [55, 75, 55, 85, 40, 92, 45, 70, 45, 92, 40, 85],
        [85, 55, 75, 55, 85, 40, 92, 45, 70, 45, 92, 40],
        [40, 85, 55, 75, 55, 85, 40, 92, 45, 70, 45, 92],
        [92, 40, 85, 55, 75, 55, 85, 40, 92, 45, 70, 45],
        [45, 92, 40, 85, 55, 75, 55, 85, 40, 92, 45, 70],
        [70, 45, 92, 40, 85, 55, 75, 55, 85, 40, 92, 45],
        [45, 70, 45, 92, 40, 85, 55, 75, 55, 85, 40, 92],
        [92, 45, 70, 45, 92, 40, 85, 55, 75, 55, 85, 40],
        [40, 92, 45, 70, 45, 92, 40, 85, 55, 75, 55, 85],
        [85, 40, 92, 45, 70, 45, 92, 40, 85, 55, 75, 55],
        [55, 85, 40, 92, 45, 70, 45, 92, 40, 85, 55, 75],
    ];

    /**
     * @return float|null Null when either side has no derived value, so the
     *                    orchestrator can redistribute this component's
     *                    weight rather than scoring the gap as zero.
     */
    public function score(?string $a, ?string $b): ?float
    {
        if ($a === null || $b === null) {
            return null;
        }

        $i = array_search($a, self::ORDER, true);
        $j = array_search($b, self::ORDER, true);

        if ($i === false || $j === false) {
            return null;
        }

        return (float) self::MATRIX[$i][$j];
    }
}
