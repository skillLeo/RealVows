<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Support\ReviewerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * ⚠️ A REVIEWER MUST NOT REACH THE OPPOSITE GENDER'S PHOTOGRAPHS BY ANY ROUTE.
 *
 * Verification means a person looks at photographs of a member. For a large
 * part of this client's market, a man looking at a woman's photographs is the
 * reason she would not join. The separation is the feature.
 *
 * ⚠️ THE INTERESTING ROUTE IS NOT THE PAGE, IT IS THE IMAGE ENDPOINT.
 *
 * Hiding a thumbnail in a template changes what is on screen and nothing about
 * what is reachable. The bytes are streamed by `/admin/verifications/{id}/photo/{photo}`
 * and `/…/selfie`, and those are what a person types when they are curious. Every
 * test below hits them directly rather than asserting something about a page.
 */
class ReviewerScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        // The photo endpoint streams from the private disk. Faked so the
        // positive control exercises the scope rather than dying on IO.
        Storage::fake(\App\Services\Photos\PhotoStorageService::DISK);
    }

    private function reviewer(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function memberWithPhotos(string $gender): Verification
    {
        $profile = Profile::factory()->create([
            'gender' => $gender,
            'submitted_at' => now(),
        ]);

        Photo::create([
            'profile_id' => $profile->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$profile->id.'/a.jpg',
            'webp_path' => 'profiles/'.$profile->id.'/a.webp',
            'thumbnail_path' => 'profiles/'.$profile->id.'/a-thumb.jpg',
            'original_filename' => 'a.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);

        Storage::disk(\App\Services\Photos\PhotoStorageService::DISK)
            ->put('profiles/'.$profile->id.'/a.jpg', 'not-a-real-jpeg');
        Storage::disk(\App\Services\Photos\PhotoStorageService::DISK)
            ->put('verifications/test-selfie.jpg', 'not-a-real-jpeg');

        return Verification::factory()->create([
            'profile_id' => $profile->id,
            'status' => Verification::STATUS_PENDING,
            'selfie_path' => 'verifications/test-selfie.jpg',
        ]);
    }

    public function test_a_male_reviewer_cannot_open_a_female_members_verification(): void
    {
        $verification = $this->memberWithPhotos('woman');

        $this->actingAs($this->reviewer(ReviewerScope::MALE))
            ->get("/admin/verifications/{$verification->id}")
            ->assertNotFound();
    }

    public function test_a_female_reviewer_cannot_open_a_male_members_verification(): void
    {
        $verification = $this->memberWithPhotos('man');

        $this->actingAs($this->reviewer(ReviewerScope::FEMALE))
            ->get("/admin/verifications/{$verification->id}")
            ->assertNotFound();
    }

    /**
     * ⚠️ THE ONE THAT MATTERS. The page can be hidden and the bytes still
     * served.
     */
    public function test_a_male_reviewer_cannot_stream_a_female_members_photograph(): void
    {
        $verification = $this->memberWithPhotos('woman');
        $photo = Photo::where('profile_id', $verification->profile_id)->firstOrFail();

        $reviewer = $this->reviewer(ReviewerScope::MALE);

        /*
         * ⚠️ SIGNED URLs. These two routes carry `signed` middleware, so an
         * unsigned request is refused with 403 before the controller runs —
         * which would make this test pass without ever reaching the scope
         * check it exists to exercise.
         */
        $this->actingAs($reviewer)
            ->get(URL::signedRoute('admin.verifications.photo', [
                'verification' => $verification->id, 'photo' => $photo->id,
            ]))
            ->assertNotFound();

        $this->actingAs($reviewer)
            ->get(URL::signedRoute('admin.verifications.selfie', ['verification' => $verification->id]))
            ->assertNotFound();
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every assertion above is a 404, and a route
     * that is broken, misspelled, or missing middleware also returns 404. The
     * scope tests would all pass against a completely dead endpoint.
     *
     * This proves the RIGHT reviewer gets through, so the 404s above mean
     * "refused" rather than "there is nothing here for anyone".
     */
    public function test_the_matching_reviewer_does_reach_the_record(): void
    {
        $verification = $this->memberWithPhotos('woman');
        $photo = Photo::where('profile_id', $verification->profile_id)->firstOrFail();

        $reviewer = $this->reviewer(ReviewerScope::FEMALE);

        $this->actingAs($reviewer)
            ->get("/admin/verifications/{$verification->id}")
            ->assertOk();

        $this->actingAs($reviewer)
            ->get(URL::signedRoute('admin.verifications.photo', [
                'verification' => $verification->id, 'photo' => $photo->id,
            ]))
            ->assertOk();
    }

    public function test_the_queue_lists_only_the_reviewers_own_gender(): void
    {
        $woman = $this->memberWithPhotos('woman');
        $man = $this->memberWithPhotos('man');

        $body = $this->actingAs($this->reviewer(ReviewerScope::FEMALE))
            ->get('/admin/verifications')
            ->assertOk()
            ->getContent();

        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('"id":'.$woman->id, $decoded);
        $this->assertStringNotContainsString('"id":'.$man->id, $decoded);
    }

    /**
     * ⚠️ AN ACCOUNT WITH NO STAFF ROLE SEES NOTHING, and never gets this far
     * anyway.
     *
     * The first version of this scoped the general `admin` role to nothing, on
     * the reasoning that an unassigned account should see nothing rather than
     * everything. The reasoning was right and the premise was wrong: `admin` is
     * the reviewer role the client hires into and has held the verification
     * queue since Phase 1. Scoping it to nothing emptied the queue for the
     * person whose job it is, and two existing tests caught it.
     *
     * A member reaching this code is impossible — EnsureUserIsAdmin returns 404
     * first — so this asserts the scope itself is still closed by default for
     * any role that is not explicitly a reviewer or an owner.
     */
    public function test_a_role_that_is_not_a_reviewer_or_an_owner_sees_nothing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('guardian');

        $this->assertSame([], ReviewerScope::gendersFor($user->fresh()));
        $this->assertFalse(ReviewerScope::maySee($user->fresh(), 'woman'));
        $this->assertFalse(ReviewerScope::maySee($user->fresh(), 'man'));
    }

    public function test_the_owner_and_the_general_reviewer_are_unrestricted(): void
    {
        foreach (['super_admin', 'admin'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->assertNull(
                ReviewerScope::gendersFor($user->fresh()),
                "[{$role}] should be unrestricted."
            );
            $this->assertTrue(ReviewerScope::maySee($user->fresh(), 'woman'));
            $this->assertTrue(ReviewerScope::maySee($user->fresh(), 'man'));
        }
    }

    /**
     * ⚠️ NEITHER REVIEWER SEES A NON-BINARY MEMBER, BY DESIGN.
     *
     * The gender enum permits it and the profile request validates it, so a
     * member can choose it. Nobody has yet. The first version of this test
     * asserted they were reviewed by the female reviewer, which was my
     * assumption rather than anybody's decision.
     *
     * On a platform whose premise is that a member chooses who may look at
     * their photographs, the code must not guess. These records queue for the
     * owner instead.
     */
    public function test_neither_reviewer_sees_a_non_binary_member(): void
    {
        $verification = $this->memberWithPhotos('non_binary');

        foreach ([ReviewerScope::FEMALE, ReviewerScope::MALE] as $role) {
            $this->actingAs($this->reviewer($role))
                ->get("/admin/verifications/{$verification->id}")
                ->assertNotFound();
        }

        // ⚠️ CONTROL: and it is genuinely reachable by the owner, so the two
        // refusals above mean "queued for super admin" rather than "lost".
        $owner = User::factory()->create();
        $owner->assignRole('super_admin');

        $this->actingAs($owner->fresh())
            ->get("/admin/verifications/{$verification->id}")
            ->assertOk();
    }
}
