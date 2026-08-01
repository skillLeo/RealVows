<?php

namespace App\Services\Sign;

use Illuminate\Support\Carbon;

/**
 * INTERNAL ONLY — NEVER SURFACED TO A MEMBER.
 *
 * Derives the two values behind the affinity component of the compatibility
 * score from a date of birth. It runs silently when a member saves their
 * date of birth and writes to `profiles.sign` and `profiles.element`.
 *
 * What that component is worth lives in config/matching.php and nowhere
 * else — deliberately not repeated here, because a number written into a
 * comment goes stale the first time somebody tunes it, and a comment that
 * lies about the mechanism is worse than no comment.
 *
 * Nothing this class returns may reach a browser: not in an Inertia prop,
 * not in an API response, not in an email, not in alt text, not in a page
 * title, not in a CSS class name. The client's market — Muslim members in
 * particular — would reject a product that matched them this way, and the
 * mechanism is his commercial secret. Members only ever see a compatibility
 * score and a rationale written from what they told us about themselves.
 *
 * The `Profile` model additionally hides both columns, so an accidental
 * serialisation drops them rather than leaking them.
 *
 * Western tropical sun signs, standard date ranges.
 */
class SignCalculator
{
    /**
     * [startMonth, startDay, key, element] — ordered by start date.
     * The wrap-around period (22 Dec – 19 Jan) is handled by the fall-through
     * at the end rather than by splitting the range into two entries.
     */
    private const RANGES = [
        [1, 20, 'aquarius', 'air'],
        [2, 19, 'pisces', 'water'],
        [3, 21, 'aries', 'fire'],
        [4, 20, 'taurus', 'earth'],
        [5, 21, 'gemini', 'air'],
        [6, 21, 'cancer', 'water'],
        [7, 23, 'leo', 'fire'],
        [8, 23, 'virgo', 'earth'],
        [9, 23, 'libra', 'air'],
        [10, 23, 'scorpio', 'water'],
        [11, 22, 'sagittarius', 'fire'],
        [12, 22, 'capricorn', 'earth'],
    ];

    /**
     * @return array{sign: string, element: string}
     */
    public function for(Carbon $dateOfBirth): array
    {
        $month = (int) $dateOfBirth->month;
        $day = (int) $dateOfBirth->day;

        // Walk backwards to the first range this date is on or after.
        foreach (array_reverse(self::RANGES) as [$startMonth, $startDay, $key, $element]) {
            if ($month > $startMonth || ($month === $startMonth && $day >= $startDay)) {
                return ['sign' => $key, 'element' => $element];
            }
        }

        // Earlier than 20 January, so the period that began the previous
        // 22 December.
        return ['sign' => 'capricorn', 'element' => 'earth'];
    }
}
