<?php

namespace App\Services\Verification;

use App\Actions\RecordAuditLog;
use App\Jobs\GenerateTeaserMatches;
use App\Mail\VerificationApproved;
use App\Mail\VerificationRejected;
use App\Models\User;
use App\Models\Verification;
use App\Services\Settings\RuntimeMailConfigurator;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ManualVerificationService implements VerificationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RecordAuditLog $audit,
        private readonly RuntimeMailConfigurator $mail,
    ) {}

    public function approve(Verification $verification, User $reviewer): Verification
    {
        return DB::transaction(function () use ($verification, $reviewer) {
            $verification->update([
                'status' => Verification::STATUS_VERIFIED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'rejection_note' => null,
            ]);

            $this->audit->handle('verification.approved', $verification, [
                'profile_id' => $verification->profile_id,
            ], $reviewer);

            $this->notify($verification, new VerificationApproved($verification));

            /*
             * Introductions are built now, before the member has paid
             * anything. They log in to find people already waiting for them,
             * locked — which is the commercial engine of the product, and
             * the reason matching runs on approval rather than on purchase.
             *
             * afterCommit so the job cannot start against a transaction that
             * has not landed yet and find an unverified profile.
             */
            GenerateTeaserMatches::dispatch($verification->profile_id)->afterCommit();

            return $verification->fresh();
        });
    }

    public function reject(Verification $verification, User $reviewer, string $reason, ?string $note = null): Verification
    {
        $wasVerified = $verification->status === Verification::STATUS_VERIFIED;

        return DB::transaction(function () use ($verification, $reviewer, $reason, $note, $wasVerified) {
            /*
             * ⚠️ Losing verification withdraws every introduction to this
             * person, and returns the credit to anybody who paid for one.
             *
             * They bought an introduction to a VERIFIED member. If we can no
             * longer stand behind that person, the thing they paid for does
             * not exist, and they get their money back without having to
             * notice or ask.
             */
            if ($wasVerified && $verification->profile) {
                app(\App\Services\Matching\MatchLifecycle::class)
                    ->withdrawIntroductionsTo($verification->profile, 'verification withdrawn');
            }

            $verification->update([
                'status' => Verification::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'rejection_note' => $note,
                // Incremented so a reviewer seeing a third attempt knows to
                // look harder, and so the member's history is legible.
                'attempt_number' => $verification->attempt_number + 1,
            ]);

            $this->audit->handle('verification.rejected', $verification, [
                'profile_id' => $verification->profile_id,
                'reason' => $reason,
            ], $reviewer);

            $this->notify($verification, new VerificationRejected($verification, $reason, $note));

            return $verification->fresh();
        });
    }

    public function rejectionReasons(): array
    {
        $configured = (string) $this->settings->get('verification', 'rejection_reasons', '');

        $reasons = collect(preg_split('/\r\n|\r|\n/', $configured))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $reasons ?: ['The photos could not be matched to the verification photo'];
    }

    /**
     * Queued, never sent inline.
     *
     * On shared hosting there is no always-on worker; a cron entry drains the
     * queue each minute. Sending inside the request would make the reviewer
     * wait on an SMTP round trip for every decision, which turns a ten-second
     * review into a minute.
     */
    private function notify(Verification $verification, $mailable): void
    {
        $user = $verification->profile?->user;

        if (! $user) {
            return;
        }

        $this->mail->apply();

        Mail::to($user->email)->queue($mailable);
    }
}
