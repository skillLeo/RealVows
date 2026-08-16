<?php

namespace Tests\Feature;

use App\Mail\SomeoneInterested;
use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ⚠️ THE QUESTION MOVED TO THE MOMENT IT PROTECTS.
 *
 * A woman used to be asked to name her father before she had seen anything.
 * Most of this platform's members are reverts and previously married women with
 * nobody to name, so the site read as closed to them and they left.
 *
 * She is asked when she ACCEPTS an introduction, which is the first moment
 * anything could move between two people.
 *
 * ⚠️ HER ANSWER IS SAVED BEFORE THE QUESTION IS ASKED, AND THAT ORDERING IS
 * THE WHOLE OF THIS FILE.
 *
 * It would be natural to ask first and save afterwards. A woman who closes the
 * tab on that question would lose her decision entirely, and the introduction
 * would sit looking untouched by somebody who had in fact said yes. Being asked
 * twice costs her a moment; losing the answer costs her the introduction.
 */
class AskedAfterSheAcceptsTest extends TestCase
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
            'religion' => 'Islam',
            'submitted_at' => now(),
            'date_of_birth' => now()->subYears(30),
        ]);

        ProfilePreference::factory()->create([
            'profile_id' => $profile->id,
            'age_min' => 18,
            'age_max' => 70,
            'distance_preference' => 'anywhere',
        ]);

        Verification::factory()->verified()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    /** Her unlocked, unanswered introduction, plus his waiting row. */
    private function readyToAccept(): array
    {
        $her = $this->member('woman', 'man');
        $him = $this->member('man', 'woman');

        $herRow = MatchRecord::create([
            'seeker_profile_id' => $her->id,
            'matched_profile_id' => $him->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => null,
        ]);

        $hisRow = MatchRecord::create([
            'seeker_profile_id' => $him->id,
            'matched_profile_id' => $her->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => null,
        ]);

        return [$her, $him, $herRow->fresh(), $hisRow->fresh()];
    }

    // ── The ordering ─────────────────────────────────────────────────────

    public function test_her_acceptance_is_saved_even_though_she_is_then_asked(): void
    {
        [$her, , $herRow] = $this->readyToAccept();

        $this->actingAs($her->user)
            ->post("/matches/{$herRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED]);

        $this->assertSame(
            MatchRecord::RESPONSE_INTERESTED,
            $herRow->fresh()->response,
            'Her acceptance was not recorded before she was asked for a trusted contact. If she '
                .'closes the tab on that question her decision is gone and the introduction sits '
                .'looking untouched.',
        );
    }

    public function test_she_is_sent_to_name_somebody(): void
    {
        [$her, , $herRow] = $this->readyToAccept();

        $this->actingAs($her->user)
            ->post("/matches/{$herRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED])
            ->assertRedirect(route('trusted-contact.show'));
    }

    /**
     * ⚠️ POSITIVE CONTROL. Redirecting everybody to that screen would satisfy
     * the test above and would also send men, and women who already have a
     * verified contact, to a question that does not apply to them.
     */
    public function test_a_woman_who_already_has_one_is_not_asked_again(): void
    {
        [$her, , $herRow] = $this->readyToAccept();

        Guardian::create([
            'member_id' => $her->user_id,
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'phone' => '+18765551111',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
        ]);

        $this->actingAs($her->user)
            ->post("/matches/{$herRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED])
            ->assertRedirect(route('matches.show', $herRow->id));
    }

    public function test_a_man_is_never_asked(): void
    {
        [, $him, , $hisRow] = $this->readyToAccept();

        $this->actingAs($him->user)
            ->post("/matches/{$hisRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED])
            ->assertRedirect(route('matches.show', $hisRow->id));
    }

    // ── ⚠️ The other party learns nothing ────────────────────────────────

    /**
     * ⚠️ SILENCE WHILE SHE IS STILL BEING ASKED.
     *
     * Telling him somebody is interested would start a wait he can do nothing
     * about, on a decision that may never complete. Her answer is already
     * saved, so this holds the message rather than the member.
     */
    public function test_the_other_party_is_told_nothing_while_she_is_being_asked(): void
    {
        Mail::fake();
        Queue::fake();

        [$her, , $herRow] = $this->readyToAccept();

        $this->actingAs($her->user)
            ->post("/matches/{$herRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED]);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        Queue::assertNotPushed(\App\Jobs\AnnounceSomeoneInterested::class);
        Queue::assertNotPushed(\App\Jobs\AnnounceMutualInterest::class);
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR THE SILENCE ABOVE, AND IT IS THE ONE THAT MATTERS.
     *
     * A platform that had stopped announcing anything to anybody satisfies the
     * assertion above perfectly, and its only symptom is members who never hear
     * that somebody is interested in them. This proves the announcement still
     * fires for a member who is not waiting on a trusted contact.
     */
    public function test_the_announcement_still_fires_when_nobody_is_being_asked(): void
    {
        Queue::fake();

        [, $him, , $hisRow] = $this->readyToAccept();

        $this->actingAs($him->user)
            ->post("/matches/{$hisRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED]);

        // ⚠️ If this fails, the silence asserted above is a broken announcement
        // rather than a deliberate hold, and nobody is ever told anything.
        Queue::assertPushed(\App\Jobs\AnnounceSomeoneInterested::class);
    }

    // ── Nothing before acceptance asks her for anything ──────────────────

    public function test_she_can_reach_the_dashboard_and_her_introductions_without_one(): void
    {
        [$her] = $this->readyToAccept();

        $this->actingAs($her->user)->get('/dashboard')->assertOk();
        $this->actingAs($her->user)->get('/introductions')->assertOk();
    }

    /**
     * ⚠️ AN ACCEPTED INTRODUCTION IS NOT A STALE TEASER.
     *
     * She has acted. Expiring it on the teaser clock would delete a decision
     * she made while she was being asked a question, and the expiry sweep runs
     * every night.
     */
    public function test_an_accepted_introduction_is_not_expired_by_the_teaser_sweep(): void
    {
        [$her, , $herRow] = $this->readyToAccept();

        $this->actingAs($her->user)
            ->post("/matches/{$herRow->id}/respond", ['response' => MatchRecord::RESPONSE_INTERESTED]);

        $this->travel(200)->days();

        app(\App\Services\Matching\MatchLifecycle::class)->expireStaleTeasers();

        $this->assertNotSame(
            MatchRecord::STATUS_EXPIRED,
            $herRow->fresh()->status,
            'An introduction she had already accepted was expired while she was still being '
                .'asked who she trusts.',
        );
        $this->assertSame(MatchRecord::RESPONSE_INTERESTED, $herRow->fresh()->response);
    }
}
