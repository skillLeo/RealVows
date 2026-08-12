<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Services\Photos\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ THE JOINT PHOTOGRAPH IS THE PRIMARY CHECK, AND THE ONLY ONE.
 *
 * No government ID is collected here or anywhere in this system. A passport
 * proves who somebody is and says nothing about whether this man is that
 * woman's father, which is the only claim that matters — and Caribbean
 * documents largely cannot establish it. Collecting them would build the most
 * sensitive store on the platform in exchange for evidence of the wrong thing.
 */
class GuardianJointPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Storage::fake(PhotoStorageService::DISK);
    }

    private function registeredGuardian(): Guardian
    {
        $memberUser = User::factory()->create();
        Profile::factory()->woman()->create(['user_id' => $memberUser->id, 'first_name' => 'Aisha']);

        $guardianUser = User::factory()->create();
        $guardianUser->assignRole('guardian');

        return Guardian::create([
            'member_id' => $memberUser->id,
            'user_id' => $guardianUser->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_REGISTERED,
        ]);
    }

    /**
     * ⚠️ THE ORDERING IS THE CHECK. The code is issued only once they have
     * signed in to their own account, so a photograph holding it cannot be an
     * old picture found on a phone.
     */
    public function test_the_code_is_issued_on_first_view_and_then_kept(): void
    {
        $guardian = $this->registeredGuardian();

        $this->assertNull($guardian->joint_photo_code, 'A code existed before anybody signed in.');

        $this->actingAs($guardian->user)->get('/trusted-contact-dashboard/confirm')->assertOk();

        $first = $guardian->fresh()->joint_photo_code;
        $this->assertNotNull($first);

        // ⚠️ Stable across a refresh. Regenerating would invalidate a
        // photograph somebody is in the middle of taking.
        $this->actingAs($guardian->user)->get('/trusted-contact-dashboard/confirm')->assertOk();

        $this->assertSame($first, $guardian->fresh()->joint_photo_code);
    }

    public function test_uploading_the_photograph_moves_the_record_to_submitted(): void
    {
        $guardian = $this->registeredGuardian();

        $this->actingAs($guardian->user)->get('/trusted-contact-dashboard/confirm')->assertOk();

        $this->actingAs($guardian->user)
            ->post('/trusted-contact-dashboard/confirm', [
                'photo' => UploadedFile::fake()->image('together.jpg', 1200, 1600),
            ])
            ->assertRedirect();

        $fresh = $guardian->fresh();

        $this->assertSame(Guardian::STATUS_SUBMITTED, $fresh->status);
        $this->assertNotNull($fresh->joint_photo_path);
        $this->assertNotNull($fresh->joint_photo_uploaded_at);

        /*
         * ⚠️ PRIVATE DISK. This is a photograph of two identifiable people and
         * must never be reachable without a controller asking who is looking.
         */
        Storage::disk(PhotoStorageService::DISK)->assertExists($fresh->joint_photo_path);
        $this->assertStringStartsWith('guardians/', $fresh->joint_photo_path);
    }

    /**
     * ⚠️ SUBMITTED IS NOT VERIFIED. Uploading proves somebody sent a file, not
     * that a human looked at it. The gate reads `verified` and nothing else.
     */
    public function test_uploading_does_not_verify_the_guardian(): void
    {
        $guardian = $this->registeredGuardian();

        $this->actingAs($guardian->user)->get('/trusted-contact-dashboard/confirm');
        $this->actingAs($guardian->user)->post('/trusted-contact-dashboard/confirm', [
            'photo' => UploadedFile::fake()->image('together.jpg'),
        ]);

        $this->assertNotSame(
            Guardian::STATUS_VERIFIED,
            $guardian->fresh()->status,
            'An upload verified the trusted contact without any human looking at it.'
        );
    }

    /** ⚠️ POSITIVE CONTROL: a non-image is refused, so the rule is real. */
    public function test_a_non_image_is_refused(): void
    {
        $guardian = $this->registeredGuardian();

        $this->actingAs($guardian->user)->get('/trusted-contact-dashboard/confirm');

        $this->actingAs($guardian->user)
            ->post('/trusted-contact-dashboard/confirm', [
                'photo' => UploadedFile::fake()->create('notes.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($guardian->fresh()->joint_photo_path);
    }

    /** A member must not reach the trusted-contact area at all. */
    public function test_a_member_cannot_reach_the_confirmation_screen(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/trusted-contact-dashboard/confirm')->assertNotFound();
    }
}
