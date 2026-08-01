<?php

namespace App\Services\Matching;

use App\Mail\MatchWithdrawn;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Services\Credits\CreditService;
use App\Services\Settings\RuntimeMailConfigurator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * What happens to an introduction after it is made.
 *
 * Two rules, and both exist because the alternative is quietly unfair to a
 * member who has already paid.
 */
class MatchLifecycle
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly RuntimeMailConfigurator $mail,
    ) {}

    /**
     * ⚠️ A CREDIT COMES BACK AUTOMATICALLY when the person it bought is no
     * longer someone we can stand behind.
     *
     * They paid for an introduction to a VERIFIED member. If that person is
     * suspended, deleted, or loses their verification, the thing they bought
     * no longer exists — so the credit returns without them having to notice,
     * ask, or argue.
     *
     * This will almost never fire. That is not a reason to skip it: the one
     * time it does, a member either gets their credit back silently or finds
     * out the platform kept their money for a profile it withdrew.
     *
     * @return int How many introductions were withdrawn.
     */
    public function withdrawIntroductionsTo(Profile $subject, string $why): int
    {
        $affected = MatchRecord::query()
            ->where('matched_profile_id', $subject->id)
            ->whereIn('status', [MatchRecord::STATUS_TEASER, MatchRecord::STATUS_UNLOCKED])
            ->with('seeker.user')
            ->get();

        $withdrawn = 0;

        foreach ($affected as $match) {
            $wasPaidFor = $match->isUnlocked();

            if ($wasPaidFor) {
                // A new positive row, never a deletion of the negative one.
                $this->credits->reverseUnlock($match, "Withdrawn: {$why}");
            }

            $match->fresh()->forceFill([
                'status' => MatchRecord::STATUS_EXPIRED,
                'unlocked_at' => null,
                'unlocked_by_ledger_id' => null,
            ])->save();

            $withdrawn++;

            // Only the people who paid are told. Telling somebody a locked
            // card quietly went away is drawing attention to nothing.
            if ($wasPaidFor && $match->seeker?->user) {
                $this->notify($match, $subject);
            }
        }

        if ($withdrawn > 0) {
            Log::info('Introductions withdrawn', [
                'subject_profile_id' => $subject->id,
                'withdrawn' => $withdrawn,
                'why' => $why,
            ]);
        }

        return $withdrawn;
    }

    /**
     * ⚠️ TEASERS GO STALE. UNLOCKED MATCHES NEVER DO.
     *
     * A teaser generated three months ago was scored against a profile that
     * may have changed since, and against a pool that has certainly changed.
     * Showing it forever is showing a stale answer with a confident number
     * attached to it.
     *
     * An unlocked match is different in kind: it was paid for. It stays
     * forever, and no sweep touches it.
     *
     * Expiry is silent — no notification, no empty gap. The same nightly run
     * regenerates the member's set, so the dashboard simply shows fresh
     * people.
     *
     * @return int How many teasers were expired.
     */
    public function expireStaleTeasers(): int
    {
        $cutoff = now()->subDays(90);

        return MatchRecord::query()
            ->where('status', MatchRecord::STATUS_TEASER)
            ->where('generated_at', '<', $cutoff)
            ->update([
                'status' => MatchRecord::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    private function notify(MatchRecord $match, Profile $subject): void
    {
        try {
            $this->mail->apply();

            Mail::to($match->seeker->user->email)->send(
                new MatchWithdrawn($match->seeker->first_name, $subject->first_name)
            );
        } catch (\Throwable $e) {
            // The credit is already back. A failed email must never undo that.
            Log::warning('Could not send withdrawal notice', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
