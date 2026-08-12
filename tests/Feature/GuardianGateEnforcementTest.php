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
     * ⚠️ THE SEEKER'S OWN GATE, BEFORE ANY ROW IS WRITTEN.
     *
     * CandidatePool gates the candidate side. This is the other half: a member
     * who has not cleared her own requirement should not have introductions
     * generated for her at all, or she receives a full set she cannot act on
     * and is then told she is waiting — which reads as the product changing its
     * mind.
     */
    public function test_no_introductions_are_generated_for_an_ungated_seeker(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);

        $seeker = $this->matchable('woman');
        $candidate = $this->matchable('man');
        $this->verifiedGuardianFor($candidate);

        $written = app(\App\Services\Matching\MatchGenerator::class)->generateFor($seeker->fresh());

        $this->assertSame(0, $written, 'Introductions were generated for a member who is gated.');

        // ⚠️ CONTROL: zero is also what a broken generator returns.
        $this->verifiedGuardianFor($seeker);

        $this->assertGreaterThan(
            0,
            app(\App\Services\Matching\MatchGenerator::class)->generateFor($seeker->fresh()),
            'The generator writes nothing even for a gated-through member, so the zero above '
                .'proves nothing about the gate.'
        );
    }

    /**
     * ⚠️ AN INTRODUCTION AWAITING APPROVAL IS NOT RENDERED AT ALL.
     *
     * Not rendered and hidden — a card payload carries a first name, a city and
     * a score, and hiding it in the component would put all three in the page
     * source of a member not yet permitted to see them.
     */
    public function test_an_unapproved_introduction_does_not_reach_the_dashboard(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);

        $seeker = $this->matchable('woman');
        $other = $this->matchable('man');
        $guardian = $this->verifiedGuardianFor($seeker);
        $this->verifiedGuardianFor($other);

        $match = MatchRecord::create([
            'seeker_profile_id' => $seeker->id,
            'matched_profile_id' => $other->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => ['values' => ['raw' => 90.0]]],
            'compatibility_rationale' => 'UNIQUE-RATIONALE-FOR-THIS-TEST-ONLY',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        /*
         * ⚠️ ASSERTED ON SOMETHING UNIQUE TO THIS MATCH.
         *
         * The first version searched for `"id":1`, which also matches the
         * user id, the profile id and anything else numbered 1 in the same
         * Inertia payload. It reported a leak that was not there. A guard that
         * fires on a coincidence is the same failure as one that never fires:
         * either way the result carries no information.
         */
        $body = html_entity_decode(
            $this->actingAs($seeker->user)->get('/dashboard')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringNotContainsString(
            'UNIQUE-RATIONALE-FOR-THIS-TEST-ONLY',
            $body,
            'An introduction awaiting trusted-contact approval was sent to the browser.'
        );

        // ⚠️ CONTROL: it appears once the introduction gate is approved.
        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => Guardian::GATE_INTRODUCTION,
            'decision' => GuardianDecision::APPROVED,
            'decided_at' => now(),
        ]);

        $after = html_entity_decode(
            $this->actingAs($seeker->user)->get('/dashboard')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString(
            'UNIQUE-RATIONALE-FOR-THIS-TEST-ONLY',
            $after,
            'The introduction never appears, so the absence above proves nothing.'
        );
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
