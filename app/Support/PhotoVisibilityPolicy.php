<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Models\User;
use App\Services\Guardian\GuardianGate;
use App\Services\Matching\MatchInterest;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * When, if ever, a member may see a photograph of the person they have been
 * introduced to.
 *
 * ══════════════════════════════════════════════════════════════════════════
 * ⚠️ THIS IS A SETTING BECAUSE IT IS A RELIGIOUS QUESTION, NOT AN ENGINEERING
 * ONE
 * ══════════════════════════════════════════════════════════════════════════
 *
 * The product currently withholds photographs from members at every stage, and
 * that is STRICTER than any position in either research document. Islam
 * explicitly permits a man to look at the woman he intends to marry — the
 * Prophet ﷺ told a man proposing to look at her and gave the reason, that it
 * makes love last longer between them.
 *
 * There is also a strange consequence of never: four photographs and a
 * verification selfie are collected from every member, stored, and seen by
 * nobody in the process except the reviewing staff member and the member's own
 * trusted contact. For a member with an APPOINTED contact, who receives no
 * photographs either, literally nobody looks at them.
 *
 * ⚠️ SO THE ANSWER IS THE CLIENT'S SCHOLAR'S TO GIVE, AND THIS EXISTS SO THAT
 * WHATEVER HE RULES IS A DROPDOWN RATHER THAN A REBUILD. Hardcoding the strict
 * answer looks safe and is not: it makes the permissive ruling expensive, which
 * is a way of deciding the question by making the alternative inconvenient.
 *
 * ── ⚠️ IT FAILS CLOSED, AND THE GUARDIAN REQUIREMENT FAILS OPEN ───────────
 *
 * GuardianPolicy treats an unreadable setting as `off`, deliberately, with a
 * DO NOT "HARDEN" THIS block explaining why: locking every member out of the
 * pool because a settings row would not load is a worse failure than letting
 * them through.
 *
 * This one is the opposite and the reason is the direction of the harm. A
 * photograph shown by mistake cannot be recalled — it is on somebody's screen,
 * and on a platform built around modesty that is the injury the whole product
 * exists to prevent. A photograph withheld by mistake is a page that says less
 * than it should for as long as the fault lasts. The recoverable failure is the
 * one to choose.
 */
class PhotoVisibilityPolicy
{
    public const GROUP = 'storage';

    public const KEY = 'photo_visibility';

    /** Nobody but the reviewing staff and the member's own trusted contact. */
    public const NEVER = 'never';

    /**
     * Once the viewer's OWN trusted contact has approved this introduction.
     *
     * ⚠️ "CONTACT" HERE IS THE TRUSTED CONTACT, NOT THE EXCHANGE OF CONTACT
     * DETAILS. The two senses of the word sit three lines apart in this
     * product and confusing them would move the gate by two whole stages.
     */
    public const AFTER_CONTACT_APPROVAL = 'after_contact_approval';

    /** Once both people have said yes to each other. */
    public const AT_MUTUAL_INTEREST = 'at_mutual_interest';

    /**
     * ⚠️ THE SCHOLAR CONSTRAINED THIS SETTING PERMANENTLY, AND THE CONSTRAINT
     * OUTLIVES WHATEVER VALUE IS CHOSEN TODAY.
     *
     * His ruling: "It should be clear that permission is for ONE GLANCE.
     * Meeting in seclusion, or demanding to look REPEATEDLY, and also arranging
     * conversation between the two, is not permitted in Shariah."
     *
     * And separately: looking is "not religiously necessary. If only the women
     * of the household see her, that is also sufficient."
     *
     * ⚠️ SO `never` IS RELIGIOUSLY SAFE AND NOTHING HAS TO CHANGE. Viewing is
     * better but not obligatory, and the household route satisfies it entirely.
     *
     * ⚠️ AND ANY FUTURE VALUE MUST BE A SINGLE VIEW, NOT A STANDING PERMISSION.
     *
     * This is the part that is easy to lose. A stored image a member can reopen
     * whenever they like is repeated looking with extra steps, no matter which
     * gate released it. Relaxing this setting therefore means building a
     * one-view mechanism — shown once, then gone — and not merely flipping a
     * value from `never` to something else.
     *
     * ⚠️ DO NOT "HARDEN" THIS BY ADDING A VALUE HERE AND TREATING THE WORK AS
     * DONE. PhotoVisibilityIsConstrainedTest fails the moment this list changes,
     * so that whoever adds one has to come and read this first.
     *
     * @var list<string>
     */
    public const VALUES = [
        self::NEVER,
        self::AFTER_CONTACT_APPROVAL,
        self::AT_MUTUAL_INTEREST,
    ];

    /** @return list<string> */
    public static function levels(): array
    {
        return [self::NEVER, self::AFTER_CONTACT_APPROVAL, self::AT_MUTUAL_INTEREST];
    }

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MatchInterest $interest,
        private readonly GuardianGate $gate,
    ) {}

    public function level(): string
    {
        try {
            $value = (string) $this->settings->get(self::GROUP, self::KEY, self::NEVER);
        } catch (\Throwable $e) {
            Log::warning('photo visibility unreadable', [
                'error' => $e->getMessage(),
                'consequence' => 'Withholding every photograph from every member. Pages will '
                    .'say photographs are not shown, which is the recoverable direction.',
            ]);

            return self::NEVER;
        }

        // An unrecognised value is not a third meaning. It is a typo, and the
        // safe reading of a typo is the strict one.
        return in_array($value, self::levels(), true) ? $value : self::NEVER;
    }

    /**
     * ⚠️ THE ONE QUESTION, ASKED AT BOTH EMISSION POINTS.
     *
     * MatchPresenter asks it to decide what JSON to build; PhotoController asks
     * it again before streaming bytes. Removing a URL from a payload is not a
     * rule while the route that serves it still answers, and a link that leaked
     * into a browser history outlives any template change.
     *
     * ⚠️ THE VIEWER IS DERIVED FROM THE MATCH RATHER THAN PASSED IN. A member
     * only ever holds the seeker side of their own rows, so the seeker IS the
     * viewer — and a signature that accepted a User would accept the WRONG
     * user, which on this particular question means answering "may this person
     * see these photographs" about somebody else entirely.
     */
    public function mayMemberSee(MatchRecord $match): bool
    {
        $viewer = $match->seeker?->user;

        if ($viewer === null) {
            return false;
        }

        return match ($this->level()) {
            self::AFTER_CONTACT_APPROVAL => $this->gate->mayReleaseIntroduction($viewer, $match),
            self::AT_MUTUAL_INTEREST => $this->interest->isMutual($match),

            /*
             * ⚠️ NEVER IS THE DEFAULT ARM AS WELL AS AN EXPLICIT ONE. A level
             * added to the constants and forgotten here falls through to the
             * strict answer rather than to the permissive one.
             */
            default => false,
        };
    }

    /**
     * Why a member is not being shown photographs, in words for the page.
     *
     * ⚠️ SAID PLAINLY RATHER THAN LEFT AS A GAP. A card with no picture and no
     * comment on it reads as a card that failed to load one, and the member
     * reasonably reports it as broken. The rule is not embarrassing and does
     * not need hiding.
     */
    public function explanation(): string
    {
        return match ($this->level()) {
            self::AFTER_CONTACT_APPROVAL => 'Photographs are shown once your trusted contact '
                .'has looked at this introduction and approved it.',
            self::AT_MUTUAL_INTEREST => 'Photographs are shown once you have both said yes '
                .'to each other.',
            default => 'Photographs are not shown to members. Your trusted contact sees them '
                .'and advises you.',
        };
    }
}
