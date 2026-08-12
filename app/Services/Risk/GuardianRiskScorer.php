<?php

namespace App\Services\Risk;

use App\Models\Guardian;
use App\Models\User;

/**
 * How likely is it that this trusted contact is not a real second person?
 *
 * ── ⚠️ RULES DO THE ARITHMETIC. THE MODEL DOES NOT. ───────────────────────
 *
 * Every point below is decided by a rule, because every signal here is a fact
 * a database can check: is this the same device, is the photograph missing, has
 * this person ever signed in on their own. Sending that to a language model
 * would be asking a probabilistic system to answer a deterministic question,
 * and it would give a slightly different answer each time for no benefit.
 *
 * The model's only job is judgement — whether a name and relationship read
 * plausibly together, and a two-sentence summary for the admin. It never sees a
 * photograph and it never contributes a number.
 *
 * ── ⚠️ IT CANNOT REJECT ANYBODY ───────────────────────────────────────────
 *
 * This returns a RiskAssessment, which has no verdict method. There is nothing
 * to call that would decline a person. The highest band still permits approval;
 * it only requires the admin to type a reason.
 *
 * ⚠️ ONE SIGNAL IS DESCRIBED IN THE BRIEF AS "auto-reject": a guardian phone
 * that matches a member account. It is NOT auto-rejecting here. It is caught
 * earlier, at nomination, by GuardianIntegrity, which refuses to create the
 * record at all — a human never sees it because it never exists. By the time
 * anything reaches this scorer the record is real, and at that point a machine
 * declining a person is exactly what the brief forbids.
 */
class GuardianRiskScorer
{
    /** Every deterministic signal and what it is worth. */
    public const SIGNALS = [
        'same_device' => 40,
        'joint_photo_missing' => 50,
        'never_signed_in' => 25,
        'activity_shadows_member' => 20,
        'impossible_age_gap' => 40,
        'contact_resembles_member' => 30,
    ];

    /**
     * ⚠️ NOT NULLABLE. The first version took `?RiskNarrator $narrator = null`
     * so the scorer could run standalone, and Laravel's container obligingly
     * passed null for it — so the model half was silently never called and two
     * tests caught it. A dependency that is optional in the signature is a
     * dependency that will be absent in production.
     */
    public function __construct(private readonly RiskNarrator $narrator) {}

    public function score(Guardian $guardian): RiskAssessment
    {
        $points = 0;
        $reasons = [];

        /*
         * ⚠️ THE STRONGEST SIGNAL IN THE SYSTEM. One device operating both
         * accounts is one person performing a check designed to involve two.
         */
        if ($this->sameDeviceAsMember($guardian)) {
            $points += self::SIGNALS['same_device'];
            $reasons[] = 'Signed in from the same device as the member.';
        }

        if (! $guardian->joint_photo_path) {
            $points += self::SIGNALS['joint_photo_missing'];
            $reasons[] = 'No joint photograph has been uploaded.';
        }

        if (! $guardian->user_id) {
            $points += self::SIGNALS['never_signed_in'];
            $reasons[] = 'Has never created their own account or signed in independently.';
        }

        if ($guardian->risk_reasons) {
            /*
             * Flags raised at nomination by GuardianIntegrity — impossible age
             * gaps, a number already serving several members. Carried forward
             * rather than recomputed, because the data they were derived from
             * (the declared date of birth) is not stored.
             */
            foreach ($guardian->risk_reasons as $flag) {
                $points += self::SIGNALS['impossible_age_gap'];
                $reasons[] = $flag;
            }
        }

        if ($this->contactResemblesMember($guardian)) {
            $points += self::SIGNALS['contact_resembles_member'];
            $reasons[] = 'Contact details closely resemble the member\'s own.';
        }

        // ⚠️ Capped at 100. A score of 190 tells an admin nothing more than 100
        // does, and an out-of-range number invites somebody to divide by it.
        $points = min(100, $points);

        $configured = $this->narrator->isConfigured();
        $narration = $configured ? $this->narrator->describe($guardian, $reasons) : null;

        return new RiskAssessment(
            score: $points,
            reasons: $reasons,
            summary: $narration['summary'] ?? null,
            modelVersion: $narration['model'] ?? null,

            // ⚠️ Only "unavailable" when a key IS configured and the call still
            // failed. No key is the feature being off, not an outage.
            modelUnavailable: $configured && $narration === null,
        );
    }

    /**
     * ⚠️ COMPARED AS HASHES. A device fingerprint is a tracking identifier and
     * the only question is "is this the same one", which a hash answers.
     */
    private function sameDeviceAsMember(Guardian $guardian): bool
    {
        if (! $guardian->device_fingerprint_hash) {
            return false;
        }

        $memberHash = User::where('id', $guardian->member_id)->value('device_fingerprint_hash');

        return $memberHash !== null && hash_equals($memberHash, $guardian->device_fingerprint_hash);
    }

    private function contactResemblesMember(Guardian $guardian): bool
    {
        $member = $guardian->member;

        if (! $member || ! $guardian->email || ! $member->email) {
            return false;
        }

        // Same local part on a different domain: aisha@gmail vs aisha@yahoo.
        $guardianLocal = strtolower(explode('@', $guardian->email)[0]);
        $memberLocal = strtolower(explode('@', $member->email)[0]);

        return $guardianLocal === $memberLocal;
    }
}
