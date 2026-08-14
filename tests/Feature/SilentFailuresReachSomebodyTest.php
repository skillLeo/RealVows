<?php

namespace Tests\Feature;

use App\Mail\OperationalAlert;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ⚠️ THE ALARM HAS TO BE HEARD, AND IT HAS TO STAY QUIET OTHERWISE.
 *
 * Both halves are the test. A reporter that never sends is the fault this
 * command was written to end. A reporter that sends every morning regardless is
 * the same fault wearing a friendlier face: a message that always arrives and
 * usually means nothing trains the person receiving it to archive it unread,
 * and then the one that matters lands in that habit.
 *
 * So every case here asserts BOTH that mail was sent when it should be and that
 * none was sent when it should not.
 */
class SilentFailuresReachSomebodyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ AN ISOLATED LOG. Reading the real storage/logs/laravel.log made the
     * "nothing to report" case fail on a machine whose log happened to hold
     * 1,721 lines of ordinary test noise, and would have made it PASS on a
     * clean one. Either way the result would have been about the machine.
     */
    private string $log = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        app(SettingsService::class)->set('general', 'admin_notification_email', 'ops@realvows.example');
        app(SettingsService::class)->flush();

        Mail::fake();

        // The marker is a real file. A leftover from another run would make
        // these tests pass or fail on the order they happened to execute in.
        @unlink(storage_path('app/last-failure-report.txt'));

        $this->log = tempnam(sys_get_temp_dir(), 'rv-log-');
        file_put_contents($this->log, "[2026-08-14 00:00:00] production.INFO: nothing alarming\n");
    }

    protected function tearDown(): void
    {
        if ($this->log !== '') {
            @unlink($this->log);
        }

        parent::tearDown();
    }

    private function aFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendIntroduction']),
            'exception' => "RuntimeException: SMTP refused the message\n#0 somewhere",
            'failed_at' => now(),
        ]);
    }

    public function test_it_says_nothing_when_there_is_nothing_to_say(): void
    {
        $this->artisan('realvows:report-silent-failures --log='.$this->log)
            ->expectsOutputToContain('Nothing needs a human')
            ->assertSuccessful();

        // ⚠️ QUEUED TOO. Queued mail is never "sent", so assertNothingSent
        // alone passes even when an email went out. AssertionsCanFailTest
        // caught this pairing missing here.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_failed_job_reaches_a_person(): void
    {
        /*
         * ⚠️ THE CASE THE WHOLE COMMAND EXISTS FOR. A job that fails three
         * times stops, and on this host that includes outbound email — so the
         * failure is a member waiting for an introduction the platform believes
         * it sent. Nothing read that table before this.
         */
        $this->aFailedJob();

        $this->artisan('realvows:report-silent-failures --log='.$this->log)->assertFailed();

        Mail::assertSent(OperationalAlert::class);
    }

    public function test_the_message_names_the_consequence_not_just_the_count(): void
    {
        /*
         * A person reading "1 failed job" at 7am does not know whether to care.
         * "A member may be waiting for an introduction they were never sent"
         * is the same fact and it is actionable.
         */
        $this->aFailedJob();

        $this->artisan('realvows:report-silent-failures --log='.$this->log)
            ->expectsOutputToContain('member may be waiting')
            ->assertFailed();
    }

    public function test_a_failed_delivery_reaches_a_person(): void
    {
        MessageDelivery::create([
            'user_id' => User::factory()->create()->id,
            'channel' => 'email',
            'template' => 'introduction-delivered',
            'status' => 'failed',
            'error' => 'mailbox unavailable',
            'attempts' => 3,
        ]);

        $this->artisan('realvows:report-silent-failures --log='.$this->log)->assertFailed();

        Mail::assertSentCount(1);
    }

    public function test_the_same_finding_is_not_reported_twice(): void
    {
        /*
         * ⚠️ A REPEATED ALERT IS HOW AN ALERT BECOMES BACKGROUND NOISE. The
         * marker is what stops yesterday's failure arriving again this morning
         * and every morning until somebody clears a table.
         */
        MessageDelivery::create([
            'user_id' => User::factory()->create()->id,
            'channel' => 'email',
            'template' => 'introduction-delivered',
            'status' => 'failed',
            'error' => 'mailbox unavailable',
            'attempts' => 3,
        ]);

        $this->artisan('realvows:report-silent-failures --log='.$this->log)->assertFailed();
        Mail::assertSentCount(1);

        // Second run, nothing new.
        $this->artisan('realvows:report-silent-failures --log='.$this->log)->assertSuccessful();
        Mail::assertSentCount(1);
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above depends on this command being
     * able to SEE a condition. A reporter blind to all three would pass
     * `test_it_says_nothing_when_there_is_nothing_to_say` perfectly and fail
     * silently forever, which is precisely the shape it was written to end.
     */
    public function test_it_can_see_each_of_the_three_conditions(): void
    {
        $this->aFailedJob();

        MessageDelivery::create([
            'user_id' => User::factory()->create()->id,
            'channel' => 'whatsapp',
            'template' => 'introduction-delivered',
            'status' => 'failed',
            'error' => 'no template approved',
            'attempts' => 3,
        ]);

        $this->artisan('realvows:report-silent-failures --log='.$this->log)
            ->expectsOutputToContain('Failed background jobs')
            ->expectsOutputToContain('Messages that did not reach a member')
            ->assertFailed();
    }

    public function test_a_report_with_nowhere_to_go_is_not_silent(): void
    {
        /*
         * ⚠️ THE FAULT THIS COMMAND MUST NOT ITSELF COMMIT. With no recipient
         * configured, returning quietly would make the reporter the newest
         * example of a signal arriving nowhere. It says so on the console and
         * logs at critical instead.
         */
        app(SettingsService::class)->set('general', 'admin_notification_email', '');
        app(SettingsService::class)->set('general', 'support_email', '');
        app(SettingsService::class)->flush();

        $this->aFailedJob();

        $this->artisan('realvows:report-silent-failures --log='.$this->log)
            ->expectsOutputToContain('reached nobody')
            ->assertFailed();

        // ⚠️ QUEUED TOO. Queued mail is never "sent", so assertNothingSent
        // alone passes even when an email went out. AssertionsCanFailTest
        // caught this pairing missing here.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_dry_run_neither_sends_nor_moves_the_marker(): void
    {
        $this->aFailedJob();

        $this->artisan('realvows:report-silent-failures --dry-run --log='.$this->log)->assertFailed();
        // ⚠️ QUEUED TOO. Queued mail is never "sent", so assertNothingSent
        // alone passes even when an email went out. AssertionsCanFailTest
        // caught this pairing missing here.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        // The marker did not move, so the real run still reports it.
        $this->artisan('realvows:report-silent-failures --log='.$this->log)->assertFailed();
        Mail::assertSentCount(1);
    }
}
