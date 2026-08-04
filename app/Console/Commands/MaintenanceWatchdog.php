<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Brings the site back up if a deploy died with it down.
 *
 * ⚠️ THE RECOVERY MUST NOT SHARE A FAILURE MODE WITH THE THING IT RECOVERS
 * FROM. THAT IS THE WHOLE DESIGN.
 *
 * The deploy takes the site down over SSH, syncs, and brings it back over the
 * same SSH session. The bring-back step is marked `if: always()`, which looks
 * like a safety net and is not one: if the SSH connection is what failed —
 * host unreachable, key agent gone, network partition — then the step that
 * repairs the damage fails for exactly the same reason as the step that caused
 * it. It ran, it failed, and the site stays down until a human notices.
 *
 * That nearly happened on 4 August 2026. `ssh-keyscan` timed out mid-deploy
 * and the run failed. The site survived only because the failure landed BEFORE
 * `artisan down` rather than after it. Landing on the lucky side of a step is
 * not a recovery strategy.
 *
 * This runs on the server, from cron, over no network at all. It cannot be
 * taken out by the thing it exists to repair.
 *
 * ── ⚠️ WHY CRON STILL RUNS WHILE THE SITE IS DOWN ─────────────────────────
 *
 * `artisan down` gates HTTP requests. It does not stop the scheduler, which is
 * a console process — so the one channel that still works during an outage is
 * precisely the one this uses.
 *
 * ── ⚠️ IT MUST NOT FIGHT A DELIBERATE MAINTENANCE WINDOW ──────────────────
 *
 * A human taking the site down on purpose, to restore a database or run a
 * migration by hand, must not have it yanked back up underneath them. Two
 * things prevent that: the threshold is far longer than any deploy takes, and
 * a `maintenance-hold` file switches this off entirely.
 */
class MaintenanceWatchdog extends Command
{
    protected $signature = 'realvows:maintenance-watchdog
                            {--minutes=10 : How long the site may sit down before this intervenes}
                            {--dry-run : Report what it would do and change nothing}';

    protected $description = 'Clear maintenance mode if a deploy died with the site down';

    /**
     * A deploy takes about a minute. Ten is far beyond any legitimate one and
     * still short enough that nobody sits looking at a maintenance page for an
     * hour.
     */
    private const DEFAULT_MINUTES = 10;

    /**
     * ⚠️ THE ESCAPE HATCH. `touch storage/framework/maintenance-hold` before
     * deliberate work, and delete it afterwards. Named in the log every time
     * this runs, so the way to stop it is discoverable at the moment somebody
     * wants to.
     */
    private const HOLD_FILE = 'framework/maintenance-hold';

    public function handle(): int
    {
        $downFile = storage_path('framework/down');

        if (! File::exists($downFile)) {
            return self::SUCCESS;
        }

        if (File::exists(storage_path(self::HOLD_FILE))) {
            $this->info('Site is down and a maintenance hold is in place. Leaving it alone.');

            return self::SUCCESS;
        }

        $minutes = max(1, (int) ($this->option('minutes') ?: self::DEFAULT_MINUTES));
        $downFor = (time() - File::lastModified($downFile)) / 60;

        if ($downFor < $minutes) {
            return self::SUCCESS;
        }

        $message = sprintf(
            'Site has been in maintenance for %d minutes with no hold file. A deploy almost '
                .'certainly died after taking it down. Bringing it back up.',
            (int) $downFor,
        );

        /*
         * ⚠️ LOGGED AT ERROR, NOT INFO. This firing means a deploy failed
         * halfway and nobody noticed for ten minutes. The site being back is
         * the good news; the deploy being broken is the thing somebody has to
         * act on, and a quiet recovery is how a broken deploy pipeline stays
         * broken.
         */
        Log::error('maintenance watchdog fired', [
            'down_for_minutes' => (int) $downFor,
            'action' => $this->option('dry-run') ? 'none (dry run)' : 'artisan up',
            'why' => 'The deploy brings the site back over the same SSH session it used to take '
                .'it down, so an SSH failure breaks the repair and the damage together.',
            'to_suppress' => 'touch storage/'.self::HOLD_FILE,
        ]);

        $this->warn($message);

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing changed.');

            return self::SUCCESS;
        }

        $this->call('up');

        return self::SUCCESS;
    }
}
