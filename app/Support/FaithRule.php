<?php

namespace App\Support;

/**
 * ⚠️ WHO MAY BE INTRODUCED TO WHOM, ON FAITH. THE MOST CONSEQUENTIAL RULE IN
 * THE PRODUCT, AND IT WAS MISSING UNTIL R6.
 *
 * The candidate query has held twelve conditions since the matching engine was
 * built. Faith was not one of them. It sat inside Lifestyle scoring, as part of
 * seventeen points out of a hundred, which means a low faith score could be
 * outweighed by height and hobbies and the introduction would still be made.
 *
 * ⚠️ THE DEFAULT BEHAVIOUR OF THE CODE WAS THE FORBIDDEN OUTCOME. A Muslim
 * woman on this platform would be introduced to non-Muslim men, and nothing on
 * any screen told her it could happen. This is not a misuse of the product. It
 * is what the product did, every night, unprompted.
 *
 * ── THE RULE ──────────────────────────────────────────────────────────────
 *
 *  · A Muslim woman may be introduced to Muslim men only. No exception, no
 *    override, and it is not settable by the member.
 *  · A Muslim man may be introduced to Muslim, Christian or Jewish women.
 *  · Everybody else carries no faith restriction.
 *
 * ── ⚠️ APPLIED TO THE PAIR, NOT TO THE SEEKER ─────────────────────────────
 *
 * The brief states the rule from the Muslim member's point of view, and a
 * literal reading of that would leave the forbidden pairing reachable from the
 * other side: a Hindu man seeking would still be handed a Muslim woman, because
 * HIS side carries no restriction. Same two people, same forbidden pairing,
 * arrived at by whoever the engine happened to be building a batch for.
 *
 * So both rules are evaluated against the PAIR. `permits()` is symmetric and
 * asking it twice with the arguments swapped gives the same answer.
 */
class FaithRule
{
    /**
     * ⚠️ SYNONYMS, BECAUSE THIS COLUMN WAS FREE TEXT AND THE OLD ROWS REMAIN.
     *
     * `religion` was an open text box with "Islam" as a placeholder. A woman who
     * typed "Muslim", "muslim", "Sunni" or "Islam " would not equal 'Islam' in
     * SQL, would be read as not-Muslim, and would be matched with non-Muslim men
     * — the exact harm this file exists to prevent, reintroduced by a string
     * comparison.
     *
     * The field is a fixed list from R6 onward, so new answers are canonical.
     * These exist for the rows written before that.
     */
    private const ISLAM = [
        'islam', 'muslim', 'muslimah', 'sunni', 'shia', 'shiite', 'islamic',
        'moslem', 'sunni muslim', 'shia muslim',
    ];

    private const CHRISTIANITY = [
        'christianity', 'christian', 'catholic', 'roman catholic', 'protestant',
        'anglican', 'baptist', 'methodist', 'pentecostal', 'seventh day adventist',
        'orthodox', 'orthodox christian', 'evangelical',
    ];

    private const JUDAISM = ['judaism', 'jewish', 'jew'];

    public const CANON_ISLAM = 'Islam';

    public const CANON_CHRISTIANITY = 'Christianity';

    public const CANON_JUDAISM = 'Judaism';

    /** The list offered in the builder. Free text is gone. */
    public const OPTIONS = [
        self::CANON_ISLAM => 'Islam',
        self::CANON_CHRISTIANITY => 'Christianity',
        self::CANON_JUDAISM => 'Judaism',
        'Hinduism' => 'Hinduism',
        'Buddhism' => 'Buddhism',
        'Sikhism' => 'Sikhism',
        'Rastafari' => 'Rastafari',
        'Other' => 'Another faith',
        'None' => 'No religion',
    ];

    /**
     * ⚠️ THE GENDERS THE WOMAN'S RULE APPLIES TO, AND WHY NON-BINARY IS HERE.
     *
     * The ruling is about a Muslim woman. `non_binary` is not a case the
     * classical texts address, so the code has to choose, and it chooses the
     * restrictive side: the same set already used for the modesty and trusted
     * contact rules. Being wrong in this direction means somebody is offered
     * fewer introductions. Being wrong the other way means making a pairing
     * that is forbidden by consensus.
     */
    private const WOMAN_SIDE = ['woman', 'non_binary'];

    /** The lowercase values SQL should treat as Islam. */
    public static function islamValues(): array
    {
        return self::ISLAM;
    }

    /** The lowercase values SQL should treat as Christian or Jewish. */
    public static function peopleOfTheBookValues(): array
    {
        return array_merge(self::CHRISTIANITY, self::JUDAISM);
    }

    /** The genders the Muslim-woman rule applies to. */
    public static function womanSideGenders(): array
    {
        return self::WOMAN_SIDE;
    }

    public static function appliesWomanRule(?string $gender): bool
    {
        return in_array($gender, self::WOMAN_SIDE, true);
    }

    public static function isMuslim(?string $religion): bool
    {
        return in_array(self::normalise($religion), self::ISLAM, true);
    }

    /**
     * Christian or Jewish. The only non-Muslim women a Muslim man may be
     * introduced to.
     */
    public static function isPeopleOfTheBook(?string $religion): bool
    {
        $r = self::normalise($religion);

        return in_array($r, self::CHRISTIANITY, true) || in_array($r, self::JUDAISM, true);
    }

    /**
     * ⚠️ AN UNSTATED RELIGION IS UNRESTRICTED, ON INSTRUCTION AND SAFELY.
     *
     * The brief is explicit that a missing answer must not block. It is safe
     * here for a reason worth writing down rather than trusting: `religion` is
     * a REQUIRED field on the Lifestyle step, so a profile cannot be submitted
     * without it, and an unsubmitted profile is already absent from the pool by
     * the first condition of the query. The permissive branch is therefore
     * unreachable for anybody who could be matched at all.
     *
     * ⚠️ IF religion EVER STOPS BEING REQUIRED, THIS BECOMES A HOLE. The
     * unreachability is what makes failing open acceptable, not the instruction.
     * ProfileCompleteness::REQUIRED is the thing holding it shut.
     */
    private static function stated(?string $religion): bool
    {
        return self::normalise($religion) !== '';
    }

    private static function normalise(?string $religion): string
    {
        return trim(strtolower((string) $religion));
    }

    /**
     * May these two be introduced to one another?
     *
     * ⚠️ SYMMETRIC BY CONSTRUCTION. Swapping the arguments must not change the
     * answer, and FaithRuleTest asserts exactly that over every combination.
     */
    public static function permits(
        ?string $religionA,
        ?string $genderA,
        ?string $religionB,
        ?string $genderB,
    ): bool {
        if (! self::stated($religionA) || ! self::stated($religionB)) {
            return true;
        }

        return self::sideAllows($religionA, $genderA, $religionB, $genderB)
            && self::sideAllows($religionB, $genderB, $religionA, $genderA);
    }

    /** What one person's own faith and gender permit in the other. */
    private static function sideAllows(
        ?string $religion,
        ?string $gender,
        ?string $otherReligion,
        ?string $otherGender,
    ): bool {
        if (! self::isMuslim($religion)) {
            return true;
        }

        /*
         * ⚠️ A MUSLIM WOMAN: MUSLIM MEN ONLY. Forbidden by consensus with no
         * difference of opinion in any school, which is why there is no setting
         * for this and no way for a member to turn it off.
         */
        if (in_array($gender, self::WOMAN_SIDE, true)) {
            return self::isMuslim($otherReligion);
        }

        // A Muslim man: Muslim, Christian or Jewish.
        return self::isMuslim($otherReligion) || self::isPeopleOfTheBook($otherReligion);
    }
}
