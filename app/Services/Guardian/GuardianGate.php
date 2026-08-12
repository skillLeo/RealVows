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
            default => [
                'state' => 'awaiting',
                'message' => 'We are waiting for your trusted contact to confirm. '
                    .'We will let you know as soon as they do.',
            ],
        };
    }

    private function verifiedGuardianFor(User $member): ?Guardian
    {
        return Guardian::where('member_id', $member->id)
            ->where('status', Guardian::STATUS_VERIFIED)
            ->first();
    }
}
