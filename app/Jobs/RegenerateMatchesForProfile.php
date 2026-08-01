<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\Matching\MatchGenerator;
use App\Services\Matching\MatchingFieldsHash;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tops a member's introductions back up after they edit something the engine
 * reads.
 *
 * Delayed by config('matching.regenerate_debounce_minutes') and unique per
 * profile, so somebody working through six fields queues one run rather than
 * six. The uniqueness lock is what does the debouncing: the second, third
 * and fourth saves all find a job already pending and are dropped.
 */
class RegenerateMatchesForProfile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $profileId) {}

    public function uniqueId(): string
    {
        return (string) $this->profileId;
    }

    public function uniqueFor(): int
    {
        return (config('matching.regenerate_debounce_minutes') * 60) + 300;
    }

    public function handle(MatchGenerator $generator, MatchingFieldsHash $hasher): void
    {
        $profile = Profile::with('preferences', 'verification')->find($this->profileId);

        if (! $profile || ! $profile->isVerified()) {
            return;
        }

        /*
         * Re-checked here rather than trusted from when the job was queued.
         * Somebody can change a field and change it back while the debounce
         * is still running, and re-running the engine for a net-zero edit is
         * work nobody asked for.
         */
        $current = $hasher->for($profile);

        if ($current === $profile->matching_fields_hash) {
            return;
        }

        $generator->generateFor($profile);

        $profile->forceFill(['matching_fields_hash' => $current])->save();
    }
}
