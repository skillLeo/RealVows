<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ NAMING A TRUSTED CONTACT IS THE STEP NOBODY TAKES IF IT IS A PAGE IN A MENU.
 *
 * It is not part of building a profile and it is the thing the whole platform
 * turns on. Left where it was, a member finished seven steps, landed on a
 * dashboard, felt finished, and never went looking for it.
 *
 * ⚠️ IT IS A REDIRECT, NOT A GATE. The page explains who this person is and
 * offers a way past, because forcing it would mean somebody with nobody to name
 * simply cannot finish — and that member is precisely who the appointed-contact
 * path exists for.
 */
class AfterProfileComesTheContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
    }

    /** A profile with nothing left to fill in, so submit is actually allowed. */
    private function completeMember(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');

        $profile = Profile::factory()->woman()->create([
            'user_id' => $user->id,
            'submitted_at' => null,
            'bio' => str_repeat('a considered sentence about myself and my hopes ', 30),

            /*
             * ⚠️ THE FACTORY LEAVES THIS UNSET AND THE COMPLETENESS CHECK
             * REQUIRES IT, so without it every submit here was refused and each
             * assertion below was measuring the refusal rather than the
             * redirect. The first run redirected to `/` and looked like a
             * routing fault.
             */
            'relationship_goal' => 'marriage_soon',
        ]);

        ProfilePreference::factory()->create(['profile_id' => $profile->id]);

        foreach (['passport', 'full_length', 'social', 'open'] as $slot) {
            Photo::create([
                'profile_id' => $profile->id,
                'slot' => $slot,
                'path' => "profiles/{$profile->id}/{$slot}.jpg",
                'webp_path' => null,
                'thumbnail_path' => "profiles/{$profile->id}/{$slot}-thumb.jpg",
                'original_filename' => "{$slot}.jpg",
                'mime' => 'image/jpeg',
                'size_bytes' => 1024,
                'width' => 1200,
                'height' => 1600,
            ]);
        }

        Verification::create([
            'profile_id' => $profile->id,
            'status' => Verification::STATUS_PENDING,
            'code' => 'AB12CD',
        ]);

        return $user->fresh();
    }

    public function test_finishing_a_profile_lands_on_the_trusted_contact(): void
    {
        $user = $this->completeMember();

        $this->actingAs($user)
            ->post('/profile/submit')
            ->assertRedirect('/trusted-contact');

        // And the profile really was submitted rather than refused on the way.
        $this->assertNotNull($user->profile->fresh()->submitted_at);
    }

    public function test_somebody_who_already_named_one_goes_to_the_dashboard(): void
    {
        /*
         * ⚠️ THE OTHER DIRECTION, AND WITHOUT IT THE REDIRECT IS JUST A
         * DIVERSION. Sending a member who has already done this to a page
         * asking them to do it would read as the platform having forgotten.
         */
        $user = $this->completeMember();

        Guardian::create([
            'member_id' => $user->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/profile/submit')
            ->assertRedirect('/dashboard');
    }

    public function test_the_page_they_land_on_explains_who_this_person_is(): void
    {
        /*
         * For most members this redirect is the first time they meet the idea
         * at all. A form with no explanation in front of it reads as one more
         * thing being asked of them.
         */
        $user = $this->completeMember();

        $props = $this->actingAs($user)->get('/trusted-contact')->assertOk()->viewData('page')['props'];

        $this->assertNull($props['current'], 'The explanation only shows to somebody who has named nobody.');

        $copy = require base_path('lang/en/realvows.php');

        foreach (['who_they_are', 'why_them', 'what_they_see', 'if_nobody'] as $key) {
            $this->assertNotEmpty(
                $copy['trusted_contact'][$key] ?? '',
                "The explanation is missing `{$key}`, so the page asks without saying why.",
            );
        }

        $this->assertStringContainsString(
            'we will arrange somebody',
            $copy['trusted_contact']['if_nobody'],
            'The page does not tell a member with nobody to name that they are still welcome.',
        );
    }

    public function test_it_is_a_redirect_and_not_a_gate(): void
    {
        /*
         * ⚠️ THE MEMBER CAN CARRY ON WITHOUT NAMING ANYBODY. If this ever
         * became a wall, somebody with no family to call would be unable to
         * finish, which is the exact person the appointed-contact path is for.
         */
        $user = $this->completeMember();

        $this->actingAs($user)->post('/profile/submit')->assertRedirect('/trusted-contact');

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/introductions')->assertOk();
    }
}
