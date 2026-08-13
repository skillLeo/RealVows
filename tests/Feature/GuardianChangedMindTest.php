<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Guardian\GuardianGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A trusted contact can take back a yes, and a double tap is not a pattern.
 *
 * ⚠️ THE DECISIONS TABLE IS APPEND-ONLY ON PURPOSE, so every question about it
 * is a question about how the SEQUENCE is read. Two readers were getting it
 * wrong in opposite directions:
 *
 *   · Guardian::hasApproved() asked whether an approval existed anywhere, so
 *     approve-then-decline left the gate open on a withdrawn answer.
 *   · GuardianGate counted decline ROWS, so a double tap and a reconsidered
 *     decline both counted toward the platform suggesting somebody is
 *     obstructing a member.
 *
 * Every case here is a pair. A reader stuck on "approved" and a reader stuck on
 * "declined" would each pass half of this file.
 */
class GuardianChangedMindTest extends TestCase
{
    use RefreshDatabase;

    private Profile $ward;

    /**
     * A real introduction row, because match_id is a foreign key.
     *
     * ⚠️ Each call is a DIFFERENT introduction, which is the distinction the
     * decline counter is being tested on. Reusing one id would make the
     * "five different people" case indistinguishable from the double tap.
     */
    private function introduction(): int
    {
        $other = Profile::factory()->create(['user_id' => User::factory()->create()->id]);

        return MatchRecord::create([
            'seeker_profile_id' => $this->ward->id,
            'matched_profile_id' => $other->id,
            'total_score' => 70,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_TEASER,
        ])->id;
    }

    private function guardian(): Guardian
    {
        $member = User::factory()->create();
        $this->ward = Profile::factory()->create(['user_id' => $member->id, 'first_name' => 'Aisha']);

        return Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
            'joint_photo_path' => 'guardians/1/together.jpg',
            'joint_photo_code' => 'AB12CD',
            'joint_photo_uploaded_at' => now(),
            'verified_at' => now(),
        ]);
    }

    private function decide(Guardian $guardian, string $decision, ?int $matchId, ?string $reason = null): GuardianDecision
    {
        return GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $matchId,
            'gate' => 'introduction',
            'decision' => $decision,
            'reason' => $reason,
            'decided_at' => now(),
        ]);
    }

    public function test_an_approval_on_its_own_opens_the_gate(): void
    {
        /*
         * ⚠️ CONTROL. Without this, every assertion below is satisfied by a
         * method that has simply stopped returning true, and "the gate never
         * opens" would read as a passing test suite.
         */
        $guardian = $this->guardian();
        $match = $this->introduction();
        $this->decide($guardian, GuardianDecision::APPROVED, $match);

        $this->assertTrue($guardian->hasApproved('introduction', $match));
    }

    public function test_a_decline_after_an_approval_closes_the_gate_again(): void
    {
        $guardian = $this->guardian();

        $match = $this->introduction();
        $this->decide($guardian, GuardianDecision::APPROVED, $match);
        $this->decide($guardian, GuardianDecision::DECLINED, $match);

        $this->assertFalse(
            $guardian->hasApproved('introduction', $match),
            'A trusted contact who withdrew their approval is still being treated as having approved.'
        );
    }

    public function test_an_approval_after_a_decline_opens_the_gate(): void
    {
        // The other direction, so the reader cannot simply prefer "declined".
        $guardian = $this->guardian();

        $match = $this->introduction();
        $this->decide($guardian, GuardianDecision::DECLINED, $match);
        $this->decide($guardian, GuardianDecision::APPROVED, $match);

        $this->assertTrue($guardian->hasApproved('introduction', $match));
    }

    public function test_the_sequence_is_still_kept_in_full(): void
    {
        /*
         * ⚠️ THE FIX MUST NOT HAVE COLLAPSED THE LOG. I very nearly replaced
         * the insert with updateOrCreate and a unique index, which would have
         * deleted exactly the history the support flow reads. Only the reading
         * changed.
         */
        $guardian = $this->guardian();

        $match = $this->introduction();
        $this->decide($guardian, GuardianDecision::APPROVED, $match);
        $this->decide($guardian, GuardianDecision::DECLINED, $match);
        $this->decide($guardian, GuardianDecision::APPROVED, $match);

        $this->assertSame(3, $guardian->decisions()->count());
    }

    public function test_decisions_on_other_introductions_do_not_leak(): void
    {
        $guardian = $this->guardian();

        $approved = $this->introduction();
        $declined = $this->introduction();
        $this->decide($guardian, GuardianDecision::APPROVED, $approved);
        $this->decide($guardian, GuardianDecision::DECLINED, $declined);

        $this->assertTrue($guardian->hasApproved('introduction', $approved));
        $this->assertFalse($guardian->hasApproved('introduction', $declined));
    }

    public function test_five_different_unexplained_declines_offer_support(): void
    {
        // Control for the counter, in the direction that must still fire.
        $guardian = $this->guardian();

        foreach (range(1, GuardianGate::UNEXPLAINED_DECLINES_BEFORE_SUPPORT) as $ignored) {
            $this->decide($guardian, GuardianDecision::DECLINED, $this->introduction());
        }

        $this->assertTrue(app(GuardianGate::class)->shouldOfferSupport($guardian->member));
    }

    public function test_the_same_decline_recorded_twice_is_one_decline(): void
    {
        /*
         * The double tap. Five taps on one introduction is one person declined
         * once, not a pattern of obstructing five.
         */
        $guardian = $this->guardian();

        $sameOne = $this->introduction();

        foreach (range(1, GuardianGate::UNEXPLAINED_DECLINES_BEFORE_SUPPORT) as $ignored) {
            $this->decide($guardian, GuardianDecision::DECLINED, $sameOne);
        }

        $this->assertFalse(
            app(GuardianGate::class)->shouldOfferSupport($guardian->member),
            'Tapping decline repeatedly on one introduction is being counted as a pattern across several.'
        );
    }

    public function test_a_decline_that_was_reconsidered_is_not_counted(): void
    {
        $guardian = $this->guardian();

        $declined = [];

        foreach (range(1, GuardianGate::UNEXPLAINED_DECLINES_BEFORE_SUPPORT) as $ignored) {
            $declined[] = $match = $this->introduction();
            $this->decide($guardian, GuardianDecision::DECLINED, $match);
        }

        // They went back and approved one of them.
        $this->decide($guardian, GuardianDecision::APPROVED, $declined[0]);

        $this->assertFalse(
            app(GuardianGate::class)->shouldOfferSupport($guardian->member),
            'A withdrawn decline is still counting against the trusted contact.'
        );
    }

    public function test_declines_with_a_reason_are_the_job_not_a_pattern(): void
    {
        $guardian = $this->guardian();

        foreach (range(1, GuardianGate::UNEXPLAINED_DECLINES_BEFORE_SUPPORT + 2) as $ignored) {
            $this->decide($guardian, GuardianDecision::DECLINED, $this->introduction(), 'Too far from her family.');
        }

        $this->assertFalse(app(GuardianGate::class)->shouldOfferSupport($guardian->member));
    }
}
