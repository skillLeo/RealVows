<?php

namespace Tests\Feature;

use App\Mail\VerificationApproved;
use App\Mail\VerificationRejected;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\ManualVerificationService;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerificationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Mail::fake();
    }

    private function reviewer(string $role = 'admin'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function submittedMember(): Verification
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');

        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Ayesha',
            'last_name' => 'Siddiqui',
            'date_of_birth' => now()->subYears(29)->toDateString(),
            'city' => 'Kingston',
            'country' => 'Jamaica',
            'submitted_at' => now(),
        ]);

        return $profile->verification()->create([
            'status' => Verification::STATUS_PENDING,
            'code' => 'RV-ABC234',
        ]);
    }

    public function test_the_service_is_resolved_through_its_interface(): void
    {
        // The binding is what lets an automated provider replace the manual
        // one later without the controller or the UI changing.
        $this->assertInstanceOf(ManualVerificationService::class, app(VerificationService::class));
    }

    public function test_a_reviewer_sees_the_queue_oldest_first(): void
    {
        $older = $this->submittedMember();
        $older->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();

        $newer = $this->submittedMember();
        $newer->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $this->actingAs($this->reviewer())
            ->get('/admin/verifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('verifications.data.0.id', $older->id)
                ->where('counts.pending', 2)
            );
    }

    public function test_a_member_cannot_reach_the_queue_and_gets_404(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/verifications')->assertNotFound();
    }

    public function test_approving_marks_verified_records_the_reviewer_audits_and_queues_the_email(): void
    {
        $verification = $this->submittedMember();
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post("/admin/verifications/{$verification->id}/review", ['decision' => 'approve'])
            ->assertRedirect('/admin/verifications');

        $fresh = $verification->fresh();

        $this->assertSame(Verification::STATUS_VERIFIED, $fresh->status);
        $this->assertSame($reviewer->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'verification.approved',
            'user_id' => $reviewer->id,
        ]);

        Mail::assertQueued(VerificationApproved::class);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $verification = $this->submittedMember();

        $this->actingAs($this->reviewer())
            ->post("/admin/verifications/{$verification->id}/review", ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Verification::STATUS_PENDING, $verification->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_rejecting_stores_the_reason_increments_the_attempt_and_queues_guidance(): void
    {
        $verification = $this->submittedMember();
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)->post("/admin/verifications/{$verification->id}/review", [
            'decision' => 'reject',
            'reason' => 'The note is not readable',
            'note' => 'Try somewhere with more light.',
        ])->assertRedirect('/admin/verifications');

        $fresh = $verification->fresh();

        $this->assertSame(Verification::STATUS_REJECTED, $fresh->status);
        $this->assertSame('The note is not readable', $fresh->rejection_reason);
        $this->assertSame(2, $fresh->attempt_number);

        $this->assertDatabaseHas('audit_logs', ['action' => 'verification.rejected']);
        Mail::assertQueued(VerificationRejected::class);
    }

    public function test_an_unverified_profile_never_enters_the_matchable_pool(): void
    {
        $verification = $this->submittedMember();

        // Submitted, but nobody has approved it yet.
        $this->assertSame(0, Profile::matchable()->count());

        app(VerificationService::class)->approve($verification, $this->reviewer());

        $this->assertSame(1, Profile::matchable()->count());
    }

    public function test_a_closed_account_drops_out_of_the_matchable_pool(): void
    {
        $verification = $this->submittedMember();
        app(VerificationService::class)->approve($verification, $this->reviewer());

        $this->assertSame(1, Profile::matchable()->count());

        // forceFill, not update: `is_active` is deliberately absent from the
        // fillable list, so closing an account can never happen by mass
        // assignment from a request payload. It has to be done on purpose.
        $verification->profile->user->forceFill(['is_active' => false])->save();

        $this->assertSame(0, Profile::matchable()->count());
    }

    public function test_rejection_reasons_come_from_settings(): void
    {
        $reasons = app(VerificationService::class)->rejectionReasons();

        $this->assertNotEmpty($reasons);
        $this->assertContains('The note is not readable', $reasons);
    }
}
