<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Matching\CandidatePool;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use App\Support\MatchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE GATES ARE ONLY REAL WHERE SOMETHING ASKS THEM.
 *
 * `GuardianGateTest` proves the gate returns the right answer. That is a
 * different claim from "an ungated member cannot be matched", and the gap
 * between the two is where this kind of feature usually dies: the service is
 * written, tested, green, and nothing calls it.
 *
 * These tests go through the real pool query and the real presenter.
 */
class GuardianGateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function setLevel(string $level): void
    {
        app(SettingsService::class)->set(GuardianPolicy::GROUP, GuardianPolicy::KEY, $level);
    }

    private function matchable(string $gender): Profile
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'gender' => $gender,
            'seeking' => $gender === 'man' ? 'woman' : 'man',
            'submitted_at' => now(),
        ]);

        \App\Models\Verification::factory()->verified()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    private function verifiedGuardianFor(Profile $profile): Guardian
    {
        return Guardian::create([
            'member_id' => $profile->user_id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
        ]);
    }

    /**
     * ⚠️ THE CONFIGURED STATE TODAY. A requirement nobody switched on must not
     * quietly empty the pool.
     */
    public function test_with_the_requirement_off_the_pool_is_unaffected(): void
    {
        $this->setLevel(GuardianPolicy::OFF);

        $seeker = $this->matchable('man');
        $this->matchable('woman');

        $this->assertCount(1, app(CandidatePool::class)->for($seeker));
    }

    public function test_a_woman_without_a_trusted_contact_is_not_in_the_pool_when_required(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $seeker = $this->matchable('man');
        $this->matchable('woman');

        $this->assertCount(
            0,
            app(CandidatePool::class)->for($seeker),
            'An ungated member is still being offered as a candidate.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. The assertion above expects an empty pool, and an
     * empty pool is also what a broken query, a missing verification or a wrong
     * gender preference produces. This proves the ONLY thing standing between
     * her and the pool was the trusted contact.
     */
    public function test_the_same_woman_enters_the_pool_once_her_contact_is_verified(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $seeker = $this->matchable('man');
        $candidate = $this->matchable('woman');

        $this->assertCount(0, app(CandidatePool::class)->for($seeker));

        $this->verifiedGuardianFor($candidate);

        $this->assertCount(
            1,
            app(CandidatePool::class)->for($seeker->fresh()),
            'A verified trusted contact did not admit her, so the empty pool above proves '
                .'nothing about the gate and only that the pool is empty.'
        );
    }

    /**
     * ⚠️ required_female MUST NOT GATE MEN. The level exists precisely to make
     * that distinction, and a query that gated everybody would pass the test
     * above.
     */
    public function test_required_female_does_not_gate_a_man(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $seeker = $this->matchable('woman');
        $this->verifiedGuardianFor($seeker);
        $this->matchable('man');

        $this->assertCount(1, app(CandidatePool::class)->for($seeker->fresh()));
    }

    /**
     * ⚠️ THE CONTACT GATE, AT THE EMISSION POINT.
     *
     * Mutual interest is necessary and no longer sufficient where the policy
     * requires a trusted contact.
     */
    public function test_mutual_interest_alone_does_not_release_contact_details(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);

        $seeker = $this->matchable('woman');
        $other = $this->matchable('man');
        $guardian = $this->verifiedGuardianFor($seeker);

        $match = MatchRecord::create([
            'seeker_profile_id' => $seeker->id,
            'matched_profile_id' => $other->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => ['values' => ['raw' => 90.0]]],
            'compatibility_rationale' => 'A considered match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => 'interested',
        ]);

        MatchRecord::create([
            'seeker_profile_id' => $other->id,
            'matched_profile_id' => $seeker->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => ['values' => ['raw' => 90.0]]],
            'compatibility_rationale' => 'A considered match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => 'interested',
        ]);

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertTrue($payload['interest']['mutual'], 'The fixture is not actually mutual.');
        $this->assertArrayNotHasKey(
            'contact',
            $payload,
            'Contact details were released on mutual interest alone, without the trusted '
                .'contact approving the exchange.'
        );
        $this->assertArrayNotHasKey('lastName', $payload);

        // ⚠️ CONTROL: the guardian approving the contact gate releases them.
        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => Guardian::GATE_CONTACT,
            'decision' => GuardianDecision::APPROVED,
            'decided_at' => now(),
        ]);

        $released = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayHasKey(
            'contact',
            $released,
            'The contact gate never opens, so the refusal above proves nothing.'
        );
    }
}
