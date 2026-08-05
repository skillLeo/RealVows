<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE RECOVERY PATH, DELIBERATELY EXERCISED BY STRANDING THE SITE.
 *
 * The deploy takes the site down over SSH and brings it back over the same SSH
 * session. That bring-back is marked `always()`, which looks like a safety net
 * and is not one: if SSH is what failed, the repair fails for the same reason
 * as the damage. On 4 August a deploy died on `ssh-keyscan` three times and the
 * site survived only because each failure landed BEFORE `artisan down`.
 *
 * The watchdog runs from cron ON THE SERVER, over no network, so it cannot be
 * taken out by the thing it repairs.
 *
 * ⚠️ A RECOVERY PATH NOBODY HAS EVER TRIGGERED IS A BELIEF, NOT A MECHANISM.
 *
 * This is the whole reason the test exists rather than a note saying the
 * command looks correct. It strands the application for real — writes the
 * down file, ages it past the threshold — and asserts the site comes back.
 */
class MaintenanceWatchdogTest extends TestCase
{
    private string $downFile;

    private string $holdFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->downFile = storage_path('framework/down');
        $this->holdFile = storage_path('framework/maintenance-hold');

        File::delete($this->downFile);
        File::delete($this->holdFile);
    }

    protected function tearDown(): void
    {
        File::delete($this->downFile);
        File::delete($this->holdFile);

        parent::tearDown();
    }

    /** Strand the site as a died-mid-deploy would leave it. */
    private function strand(int $minutesAgo): void
    {
        File::put($this->downFile, json_encode(['retry' => 30]));

        // Age it, because the threshold is what decides whether to intervene.
        touch($this->downFile, time() - ($minutesAgo * 60));
        clearstatcache();
    }

    /**
     * ⚠️ THE CHECK THIS FILE EXISTS FOR. A deploy died with the site down; the
     * watchdog brings it back.
     */
    public function test_it_brings_the_site_back_after_a_stranded_deploy(): void
    {
        $this->strand(minutesAgo: 30);

        $this->assertFileExists($this->downFile, 'The site was not actually stranded, so this proves nothing.');

        $this->artisan('realvows:maintenance-watchdog --minutes=10')->assertExitCode(0);

        $this->assertFileDoesNotExist(
            $this->downFile,
            'The site is still in maintenance after the watchdog ran. A deploy that dies after '
                .'artisan down would leave the client\'s live URL showing a holding page until '
                .'somebody noticed by hand.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. The test above passes just as well against a
     * watchdog that clears maintenance unconditionally — which would yank the
     * site back up underneath a deploy that is still running, mid-rsync, with
     * half the application on disk.
     */
    public function test_it_leaves_a_deploy_that_is_still_running_alone(): void
    {
        $this->strand(minutesAgo: 1);

        $this->artisan('realvows:maintenance-watchdog --minutes=10')->assertExitCode(0);

        $this->assertFileExists(
            $this->downFile,
            'The watchdog cleared maintenance one minute in. A deploy still syncing would be '
                .'exposed to members mid-rsync, with half the application on disk.'
        );
    }

    /**
     * ⚠️ A HUMAN TAKING THE SITE DOWN ON PURPOSE MUST NOT HAVE IT YANKED BACK.
     *
     * Restoring a database or running a migration by hand can take an hour.
     * Without an escape hatch the watchdog would fight the person doing it, and
     * they would find out by watching members arrive mid-restore.
     */
    public function test_a_maintenance_hold_stops_it(): void
    {
        $this->strand(minutesAgo: 120);
        File::put($this->holdFile, 'restoring the database by hand');

        $this->artisan('realvows:maintenance-watchdog --minutes=10')
            ->expectsOutputToContain('maintenance hold')
            ->assertExitCode(0);

        $this->assertFileExists($this->downFile, 'The watchdog overrode a deliberate maintenance hold.');
    }

    /** Nothing to do when the site is up. */
    public function test_it_does_nothing_when_the_site_is_up(): void
    {
        $this->assertFileDoesNotExist($this->downFile);

        $this->artisan('realvows:maintenance-watchdog')->assertExitCode(0);

        $this->assertFileDoesNotExist($this->downFile);
    }

    /** ⚠️ A dry run reports and changes nothing, so it can be run to look. */
    public function test_a_dry_run_changes_nothing(): void
    {
        $this->strand(minutesAgo: 30);

        $this->artisan('realvows:maintenance-watchdog --minutes=10 --dry-run')->assertExitCode(0);

        $this->assertFileExists($this->downFile, 'A dry run brought the site back.');
    }

    /**
     * ⚠️ IT IS SCHEDULED, NOT MERELY DEFINED.
     *
     * A recovery command that exists and is never run is the same as no
     * recovery command — and it is exactly the shape of the skeleton component
     * that sat unreferenced for weeks earlier in this project.
     */
    public function test_the_watchdog_is_actually_scheduled(): void
    {
        $schedule = File::get(base_path('routes/console.php'));

        $this->assertStringContainsString(
            'realvows:maintenance-watchdog',
            $schedule,
            'The watchdog is not scheduled, so it never runs and the site stays down.'
        );

        // Every minute: the cost of checking is a file_exists, the cost of not
        // checking is a client-facing outage nobody is watching for at 3am.
        $this->assertMatchesRegularExpression(
            '/realvows:maintenance-watchdog\'\)\s*\n\s*->everyMinute\(\)/',
            $schedule,
            'The watchdog is scheduled less often than every minute, which is how long a member '
                .'may sit looking at a holding page.'
        );
    }
}
