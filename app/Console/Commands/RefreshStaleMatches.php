<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTeaserMatches;
use App\Models\Profile;
use Illuminate\Console\Command;

/**
 * The nightly sweep.
 *
 * Tops up anybody verified whose introductions have gone stale — either
 * because they have never been matched, or because their set is older than
 * config('matching.stale_after_days') and new members have joined since.
 *
 * Chunked, because this runs on shared hosting from a one-minute cron with
 * --stop-when-empty and nothing may assume it will be allowed to finish. It
 * queues jobs rather than doing the work inline, so being killed halfway
 * costs at most the rest of one chunk and the next run picks up where this
 * one stopped.
 */
class RefreshStaleMatches extends Command
{
    protected $signature = 'realvows:refresh-matches {--limit= : Cap how many profiles are queued}';

    protected $description = 'Queue introduction refreshes for verified members whose matches have gone stale';

    public function handle(): int
    {
        $chunk = (int) config('matching.nightly_chunk');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $staleBefore = now()->subDays((int) config('matching.stale_after_days'));

        $queued = 0;

        Profile::query()
            ->matchable()
            ->where(function ($q) use ($staleBefore) {
                $q->whereNull('last_matched_at')->orWhere('last_matched_at', '<', $staleBefore);
            })
            // Longest wait first, so nobody is left at the back forever.
            ->orderByRaw('last_matched_at IS NULL DESC')
            ->orderBy('last_matched_at')
            ->chunkById($chunk, function ($profiles) use (&$queued, $limit) {
                foreach ($profiles as $profile) {
                    if ($limit !== null && $queued >= $limit) {
                        return false;
                    }

                    GenerateTeaserMatches::dispatch($profile->id);
                    $queued++;
                }

                return true;
            });

        $this->info("Queued {$queued} introduction refresh".($queued === 1 ? '' : 'es').'.');

        return self::SUCCESS;
    }
}
