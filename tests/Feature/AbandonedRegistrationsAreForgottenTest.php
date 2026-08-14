<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Services\Photos\PhotoStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ THE PRIVACY POLICY NOW STATES A NUMBER, AND A NUMBER IN A LEGAL DOCUMENT
 * THAT NO CODE KEEPS IS A LIE WITH A DATE ON IT.
 *
 * ── Why the category exists at all ────────────────────────────────────────
 *
 * Email verification used to sit on the front door, so an unconfirmed account
 * could only ever hold an address and a password hash. Now the builder is open
 * before confirmation, and an account that never confirms can hold four
 * photographs of somebody's face, their date of birth, and their father's
 * mobile number.
 *
 * ⚠️ THE PERSON WITH THE MOST TO LOSE HERE NEVER SIGNED UP. The trusted contact
 * gave us nothing and agreed to nothing — his number was typed by somebody
 * else. He cannot ask us for a copy, cannot ask us to delete it, and does not
 * know the record exists. Not keeping it forever is the only protection he has.
 *
 * ── ⚠️ WHY THIS FILE LEANS ON THE CONTROLS AND NOT THE DELETION ───────────
 *
 * "It deleted the row" is the easy half. The dangerous half is everything it
 * must NOT delete, because that failure is unrecoverable, silent, and would be
 * discovered by a member who came back to find themselves gone.
 */
class AbandonedRegistrationsAreForgottenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
    }

    private function member(?string $confirmed, string $registeredAgo, string $role = 'member'): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $confirmed,
            'created_at' => now()->sub($registeredAgo),
        ]);
        $user->assignRole($role);

        $profile = Profile::factory()->woman()->create(['user_id' => $user->id]);

        Storage::disk(PhotoStorageService::DISK)->put("profiles/{$profile->id}/passport.jpg", 'a face');

        Photo::create([
            'profile_id' => $profile->id,
            'slot' => 'passport',
            'path' => "profiles/{$profile->id}/passport.jpg",
            'webp_path' => null,
            'thumbnail_path' => null,
            'original_filename' => 'passport.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 6,
            'width' => 1200,
            'height' => 1600,
        ]);

        return $user->fresh();
    }

    public function test_an_unconfirmed_registration_past_the_period_is_forgotten(): void
    {
        $abandoned = $this->member(confirmed: null, registeredAgo: '13 months');

        $this->artisan('realvows:forget-abandoned --apply')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $abandoned->id]);
    }

    /**
     * ⚠️ THE POLICY PROMISES THE FILES, NOT ONLY THE RECORD POINTING AT THEM.
     *
     * Deleting the row and leaving the photograph is the worse outcome of the
     * two: a face on a server that no record mentions, which no audit finds and
     * no request to be forgotten can reach.
     */
    public function test_the_photographs_themselves_are_removed_from_disk(): void
    {
        $abandoned = $this->member(confirmed: null, registeredAgo: '13 months');
        $path = "profiles/{$abandoned->profile->id}/passport.jpg";

        // The fixture really did write a file, so its absence below means
        // deletion rather than a photograph that was never there.
        Storage::disk(PhotoStorageService::DISK)->assertExists($path);

        $this->artisan('realvows:forget-abandoned --apply')->assertSuccessful();

        Storage::disk(PhotoStorageService::DISK)->assertMissing($path);
    }

    // ── ⚠️ Everything it must not touch ──────────────────────────────────

    /**
     * ⚠️ POSITIVE CONTROL, AND THE ONE THAT MATTERS MOST.
     *
     * A command that deleted every member would satisfy the assertions above
     * perfectly and would empty the platform. This is a founding member of two
     * years' standing, which is exactly who a date comparison off by one
     * operator would take.
     */
    public function test_a_confirmed_member_of_long_standing_is_untouched(): void
    {
        $real = $this->member(confirmed: now()->subYears(2)->toDateTimeString(), registeredAgo: '2 years');

        $this->artisan('realvows:forget-abandoned --apply')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $real->id]);
        Storage::disk(PhotoStorageService::DISK)
            ->assertExists("profiles/{$real->profile->id}/passport.jpg");
    }

    public function test_somebody_who_registered_last_week_and_has_not_confirmed_is_kept(): void
    {
        /*
         * The whole reason verification moved is that people finish their
         * profile before they go to their inbox. Somebody mid-flow must not be
         * swept up by the thing protecting people who never came back.
         */
        $recent = $this->member(confirmed: null, registeredAgo: '6 days');

        $this->artisan('realvows:forget-abandoned --apply')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $recent->id]);
    }

    /**
     * ⚠️ STAFF ACCOUNTS CARRY A NULL TIMESTAMP TOO.
     *
     * `realvows:create-admin` does not send a confirmation email, so every
     * operator account looks exactly like an abandoned registration to a query
     * that only reads that column. Deleting one takes a working reviewer out of
     * the platform for a reason nobody would trace back to here.
     */
    public function test_an_operator_account_is_not_mistaken_for_an_abandoned_one(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subYears(3),
        ]);
        $admin->assignRole('admin');

        $this->artisan('realvows:forget-abandoned --apply')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /**
     * ⚠️ THE DEFAULT MUST NOT DESTROY. Every other scheduled command here
     * reports; this one deletes, and a person running it by hand to see what it
     * would do must not find out by losing the rows.
     */
    public function test_without_apply_it_reports_and_deletes_nothing(): void
    {
        $abandoned = $this->member(confirmed: null, registeredAgo: '13 months');

        $this->artisan('realvows:forget-abandoned')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $abandoned->id]);
        Storage::disk(PhotoStorageService::DISK)
            ->assertExists("profiles/{$abandoned->profile->id}/passport.jpg");
    }

    /**
     * ⚠️ THE DOCUMENT AND THE CODE HAVE TO AGREE, AND NOTHING ELSE MAKES THEM.
     *
     * The privacy policy says twelve months. The command defaults to twelve
     * months. Those two numbers live in different files with no connection
     * between them, so the day somebody tunes the command is the day the policy
     * quietly becomes false — and a false retention period in a legal document
     * is the kind of mistake that is only discovered by whoever it hurts.
     */
    public function test_the_stated_period_and_the_enforced_period_are_the_same(): void
    {
        $policy = strtolower(json_encode(
            (new \ReflectionMethod(\App\Http\Controllers\LegalController::class, 'privacySections'))
                ->invoke(app(\App\Http\Controllers\LegalController::class))
        ));

        $this->assertStringContainsString(
            'twelve months',
            $policy,
            'The privacy policy no longer states the retention period for unconfirmed accounts.',
        );

        $default = collect((new \App\Console\Commands\ForgetAbandonedRegistrations)->getDefinition()
            ->getOptions())['months']->getDefault();

        $this->assertSame(
            '12',
            (string) $default,
            'The privacy policy promises twelve months and the command enforces a different '
                .'number. The document is now false and only this test says so.',
        );
    }
}
