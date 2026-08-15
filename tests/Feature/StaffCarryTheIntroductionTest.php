<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE INTRODUCTIONS NOBODY IN THE FAMILY CAN MAKE.
 *
 * A woman with an appointed trusted contact has no relative to carry an
 * introduction for her, and the scholar's ruling forbids the two speaking
 * directly. Until R7 the product resolved that by handing her the man's
 * surname, email and phone — so the member with the least family received the
 * least protection.
 *
 * ⚠️ THE RULING ASKS FOR PRESENCE, NOT PERMISSION. A row saying "approved"
 * records only that somebody allowed it. This records that a named person stood
 * between two people and actually made the introduction.
 */
class StaffCarryTheIntroductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function member(string $gender, string $seeking): Profile
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'phone_country_code' => '+1',
            'phone_number' => '8765550123',
        ]);
        $user->assignRole('member');

        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'gender' => $gender,
            'seeking' => $seeking,
            'submitted_at' => now(),
        ]);

        Verification::factory()->verified()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    /** @return array{0: MatchRecord, 1: Guardian} */
    private function mutualPair(bool $appointed): array
    {
        $her = $this->member('woman', 'man');
        $him = $this->member('man', 'woman');

        $guardian = Guardian::create([
            'member_id' => $her->user_id,
            'name' => 'Yusuf Rahman',
            'relationship' => $appointed ? 'appointed' : 'father',
            'email' => 'yusuf@example.com',
            'phone' => '+18765551111',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => $appointed,
            'verified_at' => now(),
        ]);

        $rows = [];
        foreach ([[$her, $him], [$him, $her]] as [$seeker, $other]) {
            $rows[] = MatchRecord::create([
                'seeker_profile_id' => $seeker->id,
                'matched_profile_id' => $other->id,
                'total_score' => 88.0,
                'score_breakdown' => ['components' => []],
                'status' => MatchRecord::STATUS_UNLOCKED,
                'unlocked_at' => now(),
                'response' => MatchRecord::RESPONSE_INTERESTED,
            ]);
        }

        return [$rows[0]->fresh(), $guardian];
    }

    private function staff(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin->fresh();
    }

    public function test_an_appointed_pairing_appears_in_the_staff_queue(): void
    {
        [$match] = $this->mutualPair(appointed: true);

        $this->actingAs($this->staff())
            ->get('/admin/introductions')
            ->assertOk()
            ->assertSee((string) $match->id, false);
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR THE QUEUE. A page that listed nothing at all would
     * satisfy the assertion below on its own, and staff would quietly stop
     * making introductions for the members who need them most.
     */
    public function test_a_family_pairing_does_not_appear_in_it(): void
    {
        [$match] = $this->mutualPair(appointed: false);

        $this->actingAs($this->staff())
            ->get('/admin/introductions')
            ->assertOk()
            ->assertDontSee('"id":'.$match->id, false);
    }

    public function test_making_it_records_the_staff_member_by_name(): void
    {
        [$match, $guardian] = $this->mutualPair(appointed: true);
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post("/admin/introductions/{$match->id}", ['note' => 'Called them both.'])
            ->assertRedirect();

        $this->assertDatabaseHas('guardian_decisions', [
            'match_id' => $match->id,
            'guardian_id' => $guardian->id,
            'gate' => Guardian::GATE_INTRODUCTION_MADE,
            'made_by_user_id' => $staff->id,
            'reason' => 'Called them both.',
        ]);
    }

    public function test_it_leaves_the_queue_once_it_has_been_made(): void
    {
        [$match] = $this->mutualPair(appointed: true);
        $staff = $this->staff();

        $this->actingAs($staff)->post("/admin/introductions/{$match->id}");

        $this->actingAs($staff)
            ->get('/admin/introductions')
            ->assertOk()
            ->assertDontSee('"id":'.$match->id, false);
    }

    /**
     * ⚠️ THE SAME CONDITION THE QUEUE WAS BUILT FROM, ASKED AGAIN AT THE POINT
     * OF WRITING.
     *
     * Posting an id by hand skips the list entirely, and a family pairing is
     * one a trusted contact is already carrying. Staff recording themselves as
     * present on it would be a false record of who stood in the middle.
     */
    public function test_staff_cannot_record_themselves_on_a_family_pairing(): void
    {
        [$match] = $this->mutualPair(appointed: false);

        $this->actingAs($this->staff())
            ->post("/admin/introductions/{$match->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('guardian_decisions', [
            'match_id' => $match->id,
            'gate' => Guardian::GATE_INTRODUCTION_MADE,
        ]);
    }

    public function test_the_same_introduction_is_not_recorded_twice(): void
    {
        [$match] = $this->mutualPair(appointed: true);
        $staff = $this->staff();

        $this->actingAs($staff)->post("/admin/introductions/{$match->id}");
        $this->actingAs($staff)->post("/admin/introductions/{$match->id}");

        $this->assertSame(
            1,
            GuardianDecision::where('match_id', $match->id)
                ->where('gate', Guardian::GATE_INTRODUCTION_MADE)
                ->count(),
        );
    }

    public function test_a_member_cannot_reach_the_staff_queue(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');

        /*
         * ⚠️ 404 RATHER THAN 403, AND THAT IS THE HOUSE CONVENTION. A 403 tells
         * a member the route exists and that they are merely not allowed. The
         * admin area answers as though it is not there.
         */
        $this->actingAs($member)->get('/admin/introductions')->assertNotFound();
    }
}
