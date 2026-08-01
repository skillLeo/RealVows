<?php

namespace App\Services\Verification;

use App\Models\User;
use App\Models\Verification;

/**
 * The contract every verification provider must satisfy.
 *
 * Verification is manual today: a real person compares a member's four
 * photographs against a selfie of them holding a handwritten code. The client
 * calls this a differentiator and he is right — on a marriage platform, a
 * human check is worth more than an automated guess.
 *
 * It sits behind an interface because it was promised that an automated
 * provider could be plugged in later without touching the controller, the
 * queue, or the UI. Anything that wants to approve or reject depends on this,
 * never on the implementation.
 */
interface VerificationService
{
    public function approve(Verification $verification, User $reviewer): Verification;

    public function reject(Verification $verification, User $reviewer, string $reason, ?string $note = null): Verification;

    /**
     * The reasons a reviewer may choose from, configured in settings so the
     * client can change them without a deploy.
     *
     * @return array<int, string>
     */
    public function rejectionReasons(): array;
}
