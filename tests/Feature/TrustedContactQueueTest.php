<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Support\ReviewerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff queue: trusted contacts awaiting a human, and members waiting for
 * us to appoint one.
 */
class TrustedContactQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function submitted(string $gender = 'woman'): Guardian
    {
        $member = User::factory()->create();
        Profile::factory()->create(['user_id' => $member->id, 'gender' => $gender, 'first_name' => 'Aisha']);

        return Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_SUBMITTED,
            'joint_photo_path' => 'guardians/1/together.jpg',
            'joint_photo_code' => 'AB12CD',
            'joint_photo_uploaded_at' => now(),
        ]);
    }

    public function test_the_queue_lists_contacts_awaiting_review(): void
    {
        $guardian = $this->submitted();

        $body = html_entity_decode(
            $this->actingAs($this->staff())->get('/admin/trusted-contacts')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString('Yusuf Adeyemi', $body);
        $this->assertStringContainsString('AB12CD', $body);
    }

    /**
     * ⚠️ THE NO-CONTACT QUEUE IS THE ONE THAT GETS FORGOTTEN.
     *
     * A member who told us she has nobody is waiting on US. Nothing else in
     * the product surfaces her, so left off this screen she waits indefinitely
     * with no error anywhere.
     */
    public function test_members_waiting_for_an_appointment_appear(): void
    {
        $member = User::factory()->create();
        Profile::factory()->woman()->create(['user_id' => $member->id, 'first_name' => 'Nadia']);

        Guardian::create([
            'member_id' => $member->id,
            'name' => 'Awaiting appointment',
            'relationship' => 'appointed',
            'is_appointed' => true,
            'status' => Guardian::STATUS_INVITED,
        ]);

        $body = html_entity_decode(
            $this->actingAs($this->staff())->get('/admin/trusted-contacts')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString('Nadia', $body);
    }

    public function test_approving_verifies_the_contact(): void
    {
        $guardian = $this->submitted();

        $this->actingAs($this->staff())
            ->post("/admin/trusted-contacts/{$guardian->id}", ['decision' => 'verified'])
            ->assertRedirect();

        $this->assertSame(Guardian::STATUS_VERIFIED, $guardian->fresh()->status);
        $this->assertNotNull($guardian->fresh()->verified_at);
    }

    /**
     * ⚠️ A REJECTION WITHOUT A REASON IS A DEAD END THE MEMBER CANNOT ACT ON.
     */
    public function test_a_rejection_requires_a_reason(): void
    {
        $guardian = $this->submitted();

        $this->actingAs($this->staff())
            ->post("/admin/trusted-contacts/{$guardian->id}", ['decision' => 'rejected'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Guardian::STATUS_SUBMITTED, $guardian->fresh()->status);

        // ⚠️ CONTROL: with a reason it goes through.
        $this->actingAs($this->staff())
            ->post("/admin/trusted-contacts/{$guardian->id}", [
                'decision' => 'rejected',
                'reason' => 'Only one person is visible in the photograph.',
            ])
            ->assertRedirect();

        $this->assertSame(Guardian::STATUS_REJECTED, $guardian->fresh()->status);
    }

    /**
     * ⚠️ GENDER SEPARATION APPLIES HERE TOO. The joint photograph shows the
     * member, so a male reviewer must not reach a female member's any more
     * than her verification photograph.
     */
    public function test_a_male_reviewer_cannot_reach_a_female_members_joint_photograph(): void
    {
        $guardian = $this->submitted('woman');

        $this->actingAs($this->staff(ReviewerScope::MALE))
            ->get("/admin/trusted-contacts/{$guardian->id}/photo")
            ->assertNotFound();

        $this->actingAs($this->staff(ReviewerScope::MALE))
            ->post("/admin/trusted-contacts/{$guardian->id}", ['decision' => 'verified'])
            ->assertNotFound();

        $this->assertSame(Guardian::STATUS_SUBMITTED, $guardian->fresh()->status);
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every 404 above is also what a broken route
     * produces. This proves the right reviewer gets through.
     */
    public function test_the_matching_reviewer_can_act(): void
    {
        $guardian = $this->submitted('woman');

        $this->actingAs($this->staff(ReviewerScope::FEMALE))
            ->post("/admin/trusted-contacts/{$guardian->id}", ['decision' => 'verified'])
            ->assertRedirect();

        $this->assertSame(Guardian::STATUS_VERIFIED, $guardian->fresh()->status);
    }
}
