<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\Matching\MatchGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Builds a member's introductions after their profile is approved.
 *
 * ⚠️ This is the change that matters commercially: matching now runs BEFORE
 * payment, not after it. A member gets verified and immediately sees people
 * waiting for them, locked. That is the whole engine of the product.
 *
 * Unique per profile, so a double approval or a retried webhook cannot queue
 * two runs. Safe to be killed mid-run — shared hosting drains the queue from
 * a one-minute cron with --stop-when-empty, so nothing may assume it will be
 * allowed to finish. Rerunning simply picks up where it left off, because
 * the candidate pool excludes pairs a row already exists for.
 */
class GenerateTeaserMatches implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds. Backs off rather than hammering a struggling database. */
    public array $backoff = [30, 120];

    public function __construct(public int $profileId) {}

    public function uniqueId(): string
    {
        return (string) $this->profileId;
    }

    /** Long enough to survive a slow batch, short enough to self-heal. */
    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(MatchGenerator $generator): void
    {
        $profile = Profile::with('preferences', 'verification')->find($this->profileId);

        if (! $profile) {
            return;
        }

        // The gate that matters. A profile can be un-verified between the
        // job being queued and it running.
        if (! $profile->isVerified()) {
            return;
        }

        $written = $generator->generateFor($profile);

        Log::info('Introductions generated', [
            'profile_id' => $profile->id,
            'written' => $written,
        ]);
    }
}
