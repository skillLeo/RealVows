<?php

namespace Tests\Feature;

use App\Console\Commands\RemindTrustedContacts;
use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ REMINDERS MUST NOT BECOME NAGGING, AND THE FLOW MUST NOT DEADLOCK.
 *
 * A member whose introductions have stopped is waiting on somebody who may not
 * know they were asked. Two reminders, then we stop chasing and the member is
 * offered a replacement.
 */
class TrustedContactReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function guardianInvitedDaysAgo(int $days): Guardian
    {
        $member = User::factory()->create();
        Profile::factory()->woman()->create(['user_id' => $member->id]);

        $guardian = Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf'.$member->id.'@example.com',
            'status' => Guardian::STATUS_INVITED,
        ]);

        $guardian->forceFill(['created_at' => now()->subDays($days)])->save();

        return $guardian->fresh();
    }

    public function test_nobody_is_reminded_before_the_third_day(): void
    {
        $this->guardianInvitedDaysAgo(1);

        $this->artisan('realvows:remind-trusted-contacts')
            ->expectsOutputToContain('Sent 0 reminders');
    }

    public function test_a_reminder_goes_out_on_the_third_day(): void
    {
        $guardian = $this->guardianInvitedDaysAgo(RemindTrustedContacts::FIRST_REMINDER);

        $this->artisan('realvows:remind-trusted-contacts')
            ->expectsOutputToContain('Sent 1 reminder');

        $this->assertNotNull($guardian->fresh()->last_reminded_at);
    }

    /**
     * ⚠️ THE ONE THAT WOULD HAVE BEEN INVISIBLE IN TESTING.
     *
     * This runs from cron every day. Without the stamp, a guardian on day 8
     * receives a reminder every single day until they act — which is how a
     * sending domain gets blocked, and a test that runs the command once would
     * never see it.
     */
    public function test_running_twice_in_a_day_does_not_remind_twice(): void
    {
        $this->guardianInvitedDaysAgo(RemindTrustedContacts::FIRST_REMINDER);

        $this->artisan('realvows:remind-trusted-contacts')->expectsOutputToContain('Sent 1 reminder');
        $this->artisan('realvows:remind-trusted-contacts')->expectsOutputToContain('Sent 0 reminders');
    }

    /**
     * ⚠️ AT 14 DAYS WE STOP CHASING. The record is not deleted and the contact
     * is not marked rejected — they have done nothing wrong. The member is
     * offered a replacement instead.
     */
    public function test_a_stale_invitation_is_left_alone_rather_than_chased_or_rejected(): void
    {
        $guardian = $this->guardianInvitedDaysAgo(RemindTrustedContacts::OFFER_REPLACEMENT + 2);

        /*
         * ⚠️ ONE EXPECTATION, NOT TWO CHAINED.
         *
         * Both phrases are on the same output line, and chaining two
         * expectsOutputToContain matches them against separate lines — the
         * first consumes the line and the second finds nothing. The test failed
         * while the command was right, which is the direction that wastes an
         * hour on the wrong file.
         */
        $this->artisan('realvows:remind-trusted-contacts')
            ->expectsOutputToContain('Sent 0 reminders. 1 trusted contact past 14 days');

        $this->assertSame(
            Guardian::STATUS_INVITED,
            $guardian->fresh()->status,
            'A trusted contact who simply did not reply was marked rejected. They have done '
                .'nothing wrong.'
        );
    }

    /** An appointed case has nobody to email. */
    public function test_appointed_cases_are_never_reminded(): void
    {
        $member = User::factory()->create();
        Profile::factory()->woman()->create(['user_id' => $member->id]);

        Guardian::create([
            'member_id' => $member->id,
            'name' => 'Awaiting appointment',
            'relationship' => 'appointed',
            'is_appointed' => true,
            'status' => Guardian::STATUS_INVITED,
        ])->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->artisan('realvows:remind-trusted-contacts')->expectsOutputToContain('Sent 0 reminders');
    }

    /** ⚠️ POSITIVE CONTROL: the dry run reports without writing. */
    public function test_a_dry_run_reports_without_stamping(): void
    {
        $guardian = $this->guardianInvitedDaysAgo(RemindTrustedContacts::FIRST_REMINDER);

        $this->artisan('realvows:remind-trusted-contacts', ['--dry' => true])
            ->expectsOutputToContain('Would send 1 reminder');

        $this->assertNull(
            $guardian->fresh()->last_reminded_at,
            'A dry run wrote to the record.'
        );
    }
}
