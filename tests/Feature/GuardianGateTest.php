<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Guardian\GuardianGate;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ ALL FOUR REQUIREMENT LEVELS, BECAUSE THE POLICY IS A SETTING.
 *
 * `guardian_requirement` can be `required_all`, `required_female`, `optional`
 * or `off`, and the client has not chosen. Every gate reads it. A test that
 * only exercised the level somebody happened to set would be the same shape as
 * the weight test that passed because its fixture omitted the one component
 * that divided cleanly.
 */
class GuardianGateTest extends TestCase
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

    private function member(string $gender): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id, 'gender' => $gender]);

        return $user->fresh();
    }

    public function test_off_lets_everybody_through_regardless_of_gender(): void
    {
        $this->setLevel(GuardianPolicy::OFF);
        $gate = app(GuardianGate::class);

        foreach (['woman', 'man', 'non_binary'] as $gender) {
            $this->assertTrue(
                $gate->mayEnterPool($this->member($gender)),
                "[{$gender}] was gated while the requirement is off."
            );
        }
    }

    public function test_optional_lets_everybody_through(): void
    {
        $this->setLevel(GuardianPolicy::OPTIONAL);

        $this->assertTrue(app(GuardianGate::class)->mayEnterPool($this->member('woman')));
    }

    public function test_required_female_gates_women_and_not_men(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);
        $gate = app(GuardianGate::class);

        $this->assertFalse($gate->mayEnterPool($this->member('woman')));
        $this->assertTrue($gate->mayEnterPool($this->member('man')));
    }

    public function test_required_all_gates_everybody(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);
        $gate = app(GuardianGate::class);

        $this->assertFalse($gate->mayEnterPool($this->member('woman')));
        $this->assertFalse($gate->mayEnterPool($this->member('man')));
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above that expects `false` would
     * also pass against a gate that refused everybody forever. This proves a
     * verified trusted contact actually opens it.
     */
    public function test_a_verified_trusted_contact_opens_the_pool_gate(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);
        $member = $this->member('woman');

        $this->assertFalse(app(GuardianGate::class)->mayEnterPool($member));

        Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $this->assertTrue(
            app(GuardianGate::class)->mayEnterPool($member->fresh()),
            'A verified trusted contact did not open the gate, so every refusal above proves '
                .'nothing about the requirement and only that the gate is shut.'
        );
    }

    /**
     * ⚠️ AN UNVERIFIED GUARDIAN IS NOT A GUARDIAN. The row existing is not the
     * check; a human approving it is.
     */
    public function test_an_invited_but_unverified_contact_does_not_open_the_gate(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);
        $member = $this->member('woman');

        Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_INVITED,
        ]);

        $this->assertFalse(app(GuardianGate::class)->mayEnterPool($member->fresh()));
    }

    public function test_the_introduction_and_contact_gates_need_a_decision_at_that_gate(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);
        $member = $this->member('woman');
        $other = Profile::factory()->man()->create();

        $guardian = Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $member->profile->id,
            'matched_profile_id' => $other->id,
            'total_score' => 88.0,
            'score_breakdown' => ['components' => ['values' => ['raw' => 90.0]]],
            'compatibility_rationale' => 'A considered match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        $gate = app(GuardianGate::class);

        $this->assertFalse($gate->mayReleaseIntroduction($member->fresh(), $match));
        $this->assertFalse($gate->mayExchangeContact($member->fresh(), $match));

        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => Guardian::GATE_INTRODUCTION,
            'decision' => GuardianDecision::APPROVED,
            'decided_at' => now(),
        ]);

        $this->assertTrue($gate->mayReleaseIntroduction($member->fresh(), $match));

        /*
         * ⚠️ THE GATES ARE INDEPENDENT. Approving the introduction must not
         * also release contact details — that is the whole point of there
         * being three of them rather than one.
         */
        $this->assertFalse(
            $gate->mayExchangeContact($member->fresh(), $match),
            'Approving the introduction also released contact details. The gates must be '
                .'separate decisions.'
        );
    }

    /**
     * ⚠️ THE MEMBER MUST ALWAYS BE ABLE TO SEE WHY THEY ARE WAITING (B8).
     */
    public function test_the_member_is_told_what_state_they_are_in(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_ALL);
        $member = $this->member('woman');
        $gate = app(GuardianGate::class);

        $this->assertSame('none_named', $gate->stateFor($member)['state']);

        $guardian = Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_INVITED,
        ]);

        $awaiting = $gate->stateFor($member->fresh());
        $this->assertSame('awaiting', $awaiting['state']);
        $this->assertNotNull($awaiting['message'], 'A waiting member was given no reason.');

        $guardian->forceFill(['status' => Guardian::STATUS_VERIFIED])->save();
        $this->assertSame('verified', $gate->stateFor($member->fresh())['state']);
    }
}
