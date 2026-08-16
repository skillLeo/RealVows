<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Matching\CandidatePool;
use App\Services\Matching\MatchGenerator;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use App\Support\GuardianPresenter;
use App\Support\MatchPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
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
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
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

        Verification::factory()->verified()->create(['profile_id' => $profile->id]);

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

    /**
     * ⚠️ INVERTED IN R8, NOT DELETED, AND THE INVERSION IS THE RECORD.
     *
     * This asserted 0: a woman without a trusted contact was absent from every
     * candidate query. She was asked to name her father at signup, before she
     * had seen a single thing the product does, and most of this platform's
     * members are reverts and previously married women with nobody to name. The
     * site read as closed to them and they left.
     *
     * ⚠️ THE REQUIREMENT MOVED TO ACCEPTANCE. IT DID NOT SOFTEN. Nothing before
     * that point exposes anybody, and TheContactGateHasNotMovedTest holds the
     * other half shut: no surname, email or phone without a verified contact
     * who has approved.
     *
     * A deleted test would have left no trace that this behaviour ever existed
     * or why it changed.
     */
    public function test_a_woman_without_a_trusted_contact_is_now_in_the_pool(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $seeker = $this->matchable('man');
        $this->matchable('woman');

        $this->assertCount(
            1,
            app(CandidatePool::class)->for($seeker),
            'A woman with no trusted contact is being withheld from the pool. She is asked when '
                .'she accepts an introduction now, so gating her here means she never receives '
                .'one to accept.'
        );
    }

    /**
     * ⚠️ ALSO INVERTED. It proved a verified contact was what ADMITTED her.
     *
     * Now a trusted contact makes no difference to pool membership in either
     * direction, which is the point: naming one is no longer the price of being
     * seen. It still decides everything at the contact gate.
     */
    public function test_a_verified_contact_changes_nothing_about_pool_membership(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $seeker = $this->matchable('man');
        $candidate = $this->matchable('woman');

        $this->assertCount(1, app(CandidatePool::class)->for($seeker));

        $this->verifiedGuardianFor($candidate);

        $this->assertCount(
            1,
            app(CandidatePool::class)->for($seeker->fresh()),
            'Naming a trusted contact changed whether she is visible in the pool. It should '
                .'change only what happens at the contact gate.'
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
    /**
     * ⚠️ INVERTED IN R8. It asserted 0 — the generator refused to write a single
     * introduction for a member who had not named a trusted contact.
     *
     * That check lived in MatchGenerator alongside the one in CandidatePool,
     * and removing only one of them would have been the worse outcome of the
     * two: she would appear in everybody else's candidate query and still
     * receive nothing herself, which looks exactly like a platform with nobody
     * on it. Both went together.
     */
    public function test_introductions_are_generated_before_she_names_anybody(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);

        $seeker = $this->matchable('woman');
        $candidate = $this->matchable('man');
        $this->verifiedGuardianFor($candidate);

        $written = app(MatchGenerator::class)->generateFor($seeker->fresh());

        $this->assertGreaterThan(
            0,
            $written,
            'Nothing was generated for a woman who has not named a trusted contact. She is '
                .'asked when she accepts one, so she has to receive one first.'
        );

        /*
         * ⚠️ CONTROL: NAMING ONE MUST NOT BREAK GENERATION EITHER.
         *
         * A fresh candidate is needed because the first call already wrote a
         * row for the only one available, and `noExistingRow` correctly refuses
         * to introduce the same two people twice. Without a new person here
         * this would assert against an exhausted pool and fail for a reason
         * that has nothing to do with trusted contacts.
         */
        $this->verifiedGuardianFor($seeker);
        $this->verifiedGuardianFor($this->matchable('man'));

        $this->assertGreaterThan(
            0,
            app(MatchGenerator::class)->generateFor($seeker->fresh()),
            'The generator stopped writing once a trusted contact existed, so naming one now '
                .'costs a member the introductions she was already receiving.'
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

        /*
         * ⚠️ THE MAN NEEDS ONE TOO UNDER REQUIRED_ALL, AND THE FIXTURE USED TO
         * SKIP IT.
         *
         * Contact release is now a question about the PAIR rather than about
         * whoever's row is being read, so a man with no verified contact under
         * a policy that requires one holds the exchange shut — correctly. The
         * old fixture only worked because his side was never consulted, which
         * is the same gap that let him receive her surname and phone number.
         */
        $hisGuardian = $this->verifiedGuardianFor($other);

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

        // ⚠️ CONTROL: both trusted contacts approving releases the exchange.
        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => Guardian::GATE_CONTACT,
            'decision' => GuardianDecision::APPROVED,
            'decided_at' => now(),
        ]);

        // His decision lives on HIS row, which is why it is looked up rather
        // than recorded against the same match id.
        $hisRow = MatchRecord::where('seeker_profile_id', $other->id)
            ->where('matched_profile_id', $seeker->id)
            ->firstOrFail();

        GuardianDecision::create([
            'guardian_id' => $hisGuardian->id,
            'match_id' => $hisRow->id,
            'gate' => Guardian::GATE_CONTACT,
            'decision' => GuardianDecision::APPROVED,
            'decided_at' => now(),
        ]);

        $released = app(MatchPresenter::class)->full($match->fresh());

        /*
         * ⚠️ THE CONTROL MOVED, BECAUSE THE RELEASE MOVED. It used to assert
         * the MEMBER receives the details once the gate opens. She does not any
         * more: the client's scholar ruled that a family guardian must be
         * present for the conversation rather than merely permitting it, so the
         * details go to him and he makes the introduction.
         *
         * The control's job is unchanged — prove the gate opens rather than
         * being permanently shut — so it follows the release to where the
         * release now happens. Left asserting the old behaviour it would have
         * failed for the right reason and read like a regression.
         */
        $this->assertArrayNotHasKey('contact', $released, 'Details reached the member directly.');
        $this->assertTrue(
            $released['introductionIsBeingMade'],
            'The contact gate never opens, so the refusal above proves nothing.'
        );

        $held = app(GuardianPresenter::class)->introduction(
            $guardian->fresh(),
            $match->fresh(),
            $other,
        );

        $this->assertArrayHasKey(
            'contact',
            $held,
            'Nobody holds the contact details at all, so the introduction is a dead end.'
        );
    }
}
