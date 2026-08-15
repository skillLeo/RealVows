<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\User;
use App\Support\GuardianPolicy;

/**
 * The three points a trusted contact has to approve, and whether they have.
 *
 * ── ⚠️ EVERY GATE ASKS THE POLICY FIRST ───────────────────────────────────
 *
 * `guardian_requirement` is a setting with four levels and it is deliberately
 * unset. When it is `off`, every method here returns "allowed" without looking
 * at anything else, because there is no requirement to satisfy. That is not a
 * bypass — it is the configured state of the product, and the client has not
 * chosen otherwise yet.
 *
 * ⚠️ THE ORDER MATTERS. Policy, then requirement, then the guardian record. A
 * gate that looked up the guardian first would 500 for every member who has
 * none, on a platform where having none is the normal case today.
 *
 * ── ⚠️ WHY THESE ARE READ-ONLY QUESTIONS ──────────────────────────────────
 *
 * Nothing here writes a decision or sends anything. A gate that notified on
 * being asked would fire every time a page rendered. Asking is free and must
 * stay free; `GuardianDashboardController::decide` is the only writer.
 */
class GuardianGate
{
    public function __construct(private readonly GuardianPolicy $policy) {}

    /** May this member be matched at all? */
    public function mayEnterPool(User $member): bool
    {
        if (! $this->policy->isRequiredFor($member->profile?->gender)) {
            return true;
        }

        return $this->verifiedGuardianFor($member) !== null;
    }

    /** May this introduction be shown to the member? */
    public function mayReleaseIntroduction(User $member, MatchRecord $match): bool
    {
        if (! $this->policy->isRequiredFor($member->profile?->gender)) {
            return true;
        }

        $guardian = $this->verifiedGuardianFor($member);

        return $guardian?->hasApproved(Guardian::GATE_INTRODUCTION, $match->id) ?? false;
    }

    /** May contact details move between these two people? */
    public function mayExchangeContact(User $member, MatchRecord $match): bool
    {
        if (! $this->policy->isRequiredFor($member->profile?->gender)) {
            return true;
        }

        $guardian = $this->verifiedGuardianFor($member);

        return $guardian?->hasApproved(Guardian::GATE_CONTACT, $match->id) ?? false;
    }

    /**
     * ⚠️ DOES THE TRUSTED CONTACT MAKE THE INTRODUCTION HIMSELF, RATHER THAN
     * MERELY PERMITTING IT?
     *
     * ── The client's scholar answered this, and the answer changed the flow ──
     *
     * The question put to him was whether a guardian APPROVING an exchange is
     * the same as a guardian being PRESENT for it. Until now the product
     * assumed it was: he approved at the third gate, the surname and phone
     * number appeared on the member's own screen, and the two of them spoke
     * privately with nobody else involved. He gated the meeting. He did not
     * attend it.
     *
     * The ruling was that approving is NOT the same, except in specific
     * circumstances: where the guardian is unjust, is not Muslim, or has died.
     * Outside those, he must be present.
     *
     * ⚠️ AND THOSE CIRCUMSTANCES ARE EXACTLY WHAT `is_appointed` ALREADY MEANS.
     * A member reaches an appointed contact precisely when there is no family
     * guardian able to stand for her: nobody to name, or nobody suitable. So
     * the exception the ruling carves out is a flag this system has held from
     * the beginning, and no new concept is needed to express it.
     *
     *   · Family contact  → he receives the details and introduces them.
     *   · Appointed contact → the member receives them directly, as before.
     *   · No contact required → unchanged.
     *
     * ⚠️ THIS IS A JUDGEMENT ABOUT MEN AND THE CLIENT MAY REVERSE IT. Mediation
     * is applied to anybody holding a verified family contact rather than to
     * women alone. The requirement is `required_female`, so in practice a man
     * only has one if he chose to name one, and routing his introduction
     * through the person he chose is the consistent reading. Narrowing it to
     * women is a one-line change if he wants that instead.
     */
    /** Nobody stands between these two. Details move directly. */
    public const MEDIATION_NONE = 'none';

    /** Her own trusted contact holds the details and approaches on her behalf. */
    public const MEDIATION_GUARDIAN = 'guardian';

    /** RealVows staff hold them and make the introduction, recorded as present. */
    public const MEDIATION_STAFF = 'staff';

    /**
     * ⚠️ WHO STANDS BETWEEN THESE TWO PEOPLE, AND THE SCHOLAR SETTLED IT.
     *
     * His ruling: "Meeting in seclusion, or demanding to look repeatedly, and
     * also ARRANGING CONVERSATION BETWEEN THE TWO, is not permitted in Shariah.
     * Whatever necessary matters need to be satisfied should be handled through
     * the women of the household."
     *
     * So the question is never "has somebody approved" — it is "who carries
     * this". Approval is not presence.
     *
     * ── ⚠️ AN APPOINTED CONTACT NOW MEDIATES, VIA STAFF ──────────────────
     *
     * This method used to return false for an appointed contact, which meant a
     * woman with no family to name received the man's surname, email and phone
     * on her own screen and could contact him directly. That was defended as
     * the scholar's own exception — he permits a guardian being bypassed where
     * one is unjust, not Muslim, or dead.
     *
     * ⚠️ THE EXCEPTION DOES NOT REACH THIS CASE. "No family to name" is not
     * "guardian is unjust, non-Muslim, or dead", and the ruling names no
     * exception at all to conversation between the two. So the woman with the
     * least family was getting the least protection, which is exactly backwards.
     * Staff carry it instead.
     */
    public function mediationFor(User $member): string
    {
        $guardian = $this->verifiedGuardianFor($member);

        if ($guardian === null) {
            return self::MEDIATION_NONE;
        }

        /*
         * ⚠️ THE APPOINTED CONTACT STILL RECEIVES NOTHING. He is a stranger the
         * platform placed in that seat, and handing him a woman's phone number
         * so he could pass it on would be a worse exposure than the one being
         * prevented. Staff carry it, and are recorded as having done so.
         */
        return $guardian->is_appointed
            ? self::MEDIATION_STAFF
            : self::MEDIATION_GUARDIAN;
    }

    public function contactIsMediated(User $member): bool
    {
        return $this->mediationFor($member) !== self::MEDIATION_NONE;
    }

    /**
     * What the member should be told, in their own words.
     *
     * ⚠️ NEVER LET A FLOW DEADLOCK WITH NO VISIBLE REASON (B8). A member whose
     * introductions have stopped must be able to see why on their own
     * dashboard. Silence reads as the product being broken, and the member
     * cannot chase somebody they were never told they were waiting on.
     */
    public function stateFor(User $member): array
    {
        if (! $this->policy->isRequiredFor($member->profile?->gender)) {
            return ['state' => 'not_required', 'message' => null];
        }

        $guardian = Guardian::where('member_id', $member->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->latest()
            ->first();

        return match (true) {
            $guardian === null => [
                'state' => 'none_named',
                'message' => 'Add a trusted contact so we can start finding introductions for you.',
            ],
            $guardian->status === Guardian::STATUS_VERIFIED => [
                'state' => 'verified',
                'message' => null,
            ],
            $guardian->status === Guardian::STATUS_REJECTED => [
                'state' => 'rejected',
                'message' => 'We could not confirm your trusted contact. You can name somebody else.',
            ],
            /*
             * ⚠️ AT 14 DAYS SHE IS OFFERED SOMEBODY ELSE (B8).
             *
             * The reminder command stops chasing at this point, and without
             * this branch the member would simply keep reading "we are waiting"
             * forever with no way out. A flow that cannot end is worse than one
             * that ends badly: she cannot chase him, and until now nothing told
             * her she was allowed to stop waiting.
             */
            $guardian->created_at->diffInDays(now()) >= \App\Console\Commands\RemindTrustedContacts::OFFER_REPLACEMENT => [
                'state' => 'stale',
                'message' => 'Your trusted contact has not replied. You can name somebody else, '
                    .'or ask us to arrange somebody for you.',
                'mayReplace' => true,
            ],

            default => [
                'state' => 'awaiting',
                'message' => 'We are waiting for your trusted contact to confirm. '
                    .'We will let you know as soon as they do.',
            ],
        };
    }

    /**
     * ⚠️ B9. FIVE DECLINES WITH NO REASON IS A PATTERN, NOT A JUDGEMENT.
     *
     * A trusted contact is entitled to decline anybody, and declining with a
     * reason is them doing the job. Declining repeatedly and saying nothing is
     * the shape that leaves a member stuck with no way to understand or appeal
     * it, and she has no visibility of the decisions at all.
     *
     * This does not accuse anybody. It surfaces a support route to the member
     * so she can ask a human to look, which is the only fair response to a
     * question the platform cannot answer on its own.
     */
    public const UNEXPLAINED_DECLINES_BEFORE_SUPPORT = 5;

    public function shouldOfferSupport(User $member): bool
    {
        $guardian = Guardian::where('member_id', $member->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->first();

        if (! $guardian) {
            return false;
        }

        /*
         * ⚠️ FIVE PEOPLE DECLINED, NOT FIVE ROWS WRITTEN.
         *
         * This counted rows. The decisions table is append-only by design, so
         * rows and people are not the same number in two ordinary situations:
         *
         *   · A double tap on a slow connection writes the same decline twice.
         *     Counting rows, the second tap moves a trusted contact one fifth
         *     of the way toward the platform suggesting they are obstructing
         *     somebody.
         *
         *   · A contact who declines, reconsiders and approves leaves the
         *     decline in the sequence. Counting rows, the answer they withdrew
         *     still counts against them.
         *
         * Neither is a pattern. The threshold is meant to describe five
         * DIFFERENT introductions currently refused without explanation, so
         * the current decision per introduction is what gets counted.
         *
         * Done in PHP rather than SQL because it is one trusted contact's own
         * decisions — tens of rows, not thousands — and a window function here
         * would be harder to read than the rule it encodes.
         */
        $all = $guardian->decisions()
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->get();

        /*
         * ⚠️ ONLY DECISIONS THAT NAME AN INTRODUCTION CAN BE DE-DUPLICATED,
         * AND THAT LIMIT IS THE POINT.
         *
         * My first attempt grouped everything, treating a null match_id as one
         * bucket. That collapsed five separate gate-level declines into one and
         * silently rewrote a rule this change had no business touching.
         *
         * Where match_id is present the subject is knowable, so the current
         * answer for each introduction is what counts. Where it is absent there
         * is no key to compare on, and inventing one would be guessing about
         * evidence used to suggest somebody is obstructing a member. Those stay
         * one row, one decision — exactly as before.
         */
        [$perIntroduction, $gateWide] = $all->partition(fn ($d) => $d->match_id !== null);

        $current = $perIntroduction
            ->groupBy('match_id')
            ->map(fn ($forOneIntroduction) => $forOneIntroduction->first())
            ->values()
            ->concat($gateWide);

        $unexplained = $current->filter(
            fn ($decision) => $decision->decision === \App\Models\GuardianDecision::DECLINED
                && trim((string) $decision->reason) === ''
        );

        return $unexplained->count() >= self::UNEXPLAINED_DECLINES_BEFORE_SUPPORT;
    }

    private function verifiedGuardianFor(User $member): ?Guardian
    {
        return Guardian::where('member_id', $member->id)
            ->where('status', Guardian::STATUS_VERIFIED)
            ->first();
    }
}
