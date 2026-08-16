<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use App\Services\Matching\CandidatePool;
use App\Support\MatchPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ WHAT MOVED IS WHEN WE ASK. WHAT DID NOT MOVE IS WHETHER WE REQUIRE.
 *
 * R8 removed the trusted contact from pool entry. A woman can now register,
 * build a profile, be verified, enter the pool and receive introductions
 * without naming anybody, because most of this platform's members are reverts
 * and previously married women with no father or brother to name, and asking
 * first made the site read as closed to them.
 *
 * That is safe for a specific reason, and this file exists so the reason cannot
 * quietly stop being true: nothing before acceptance exposes anybody. A sealed
 * teaser carries no photograph, no surname and no contact detail. The engine
 * choosing two people is not a meeting. Accepting is a private decision on her
 * own screen that the other person is never told about.
 *
 * ⚠️ THE DANGER IS A LATER READER SEEING "the trusted contact moved later" AND
 * MOVING THE GATE WITH IT. They are different things. No surname, no email and
 * no phone number may be released without a verified trusted contact who has
 * approved the exchange — no matter when in the flow that contact was named.
 */
class TheContactGateHasNotMovedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function member(string $gender, string $seeking, string $email): Profile
    {
        $user = User::factory()->create([
            'email' => $email,
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
            'last_name' => $gender === 'woman' ? 'Bakare' : 'Adeyemi',
            'submitted_at' => now(),
            'date_of_birth' => now()->subYears(30),
            'city' => 'Kingston',
            'country' => 'Jamaica',
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

    private function mutualRows(Profile $her, Profile $him): array
    {
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

        return [$rows[0]->fresh(), $rows[1]->fresh()];
    }

    // ── The half that moved ──────────────────────────────────────────────

    public function test_a_woman_with_no_trusted_contact_is_now_in_the_pool(): void
    {
        /*
         * ⚠️ INVERTED, NOT DELETED. This assertion used to read
         * assertNotContains, because a woman without a trusted contact was
         * absent from every candidate query. Turning it round leaves a record
         * that the behaviour changed on purpose; deleting it would have left
         * none.
         */
        $him = $this->member('man', 'woman', 'omar@example.com');
        $her = $this->member('woman', 'man', 'aisha@example.com');

        $candidates = app(CandidatePool::class)->query($him)->pluck('profiles.id')->all();

        $this->assertContains(
            $her->id,
            $candidates,
            'A woman with no trusted contact is missing from the pool. She is asked when she '
                .'accepts an introduction now, not before she has seen anything, and gating her '
                .'here means she never receives one to accept.',
        );
    }

    // ── The half that did not ────────────────────────────────────────────

    public function test_no_contact_detail_moves_without_a_verified_trusted_contact(): void
    {
        $her = $this->member('woman', 'man', 'aisha@example.com');
        $him = $this->member('man', 'woman', 'omar@example.com');

        [$herRow, $hisRow] = $this->mutualRows($her, $him);

        foreach ([$herRow, $hisRow] as $row) {
            $payload = app(MatchPresenter::class)->full($row);

            $this->assertArrayNotHasKey(
                'contact',
                $payload,
                'A phone number was released with no verified trusted contact in place. The '
                    .'requirement moved to acceptance, it did not go away.',
            );
            $this->assertArrayNotHasKey('lastName', $payload);
        }

        $encoded = json_encode([
            app(MatchPresenter::class)->full($herRow),
            app(MatchPresenter::class)->full($hisRow),
        ]);

        $this->assertStringNotContainsString('aisha@example.com', $encoded);
        $this->assertStringNotContainsString('omar@example.com', $encoded);
        $this->assertStringNotContainsString('Bakare', $encoded);
        $this->assertStringNotContainsString('Adeyemi', $encoded);
    }

    public function test_naming_one_is_not_enough_it_has_to_be_verified_and_approving(): void
    {
        $her = $this->member('woman', 'man', 'aisha@example.com');
        $him = $this->member('man', 'woman', 'omar@example.com');

        [$herRow] = $this->mutualRows($her, $him);

        // Named, invited, and not yet verified. Nothing may move on this.
        Guardian::create([
            'member_id' => $her->user_id,
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'phone' => '+18765551111',
            'status' => Guardian::STATUS_INVITED,
            'is_appointed' => false,
        ]);

        $payload = app(MatchPresenter::class)->full($herRow->fresh());

        $this->assertArrayNotHasKey('contact', $payload);
        $this->assertArrayNotHasKey('lastName', $payload);
    }

    /**
     * ⚠️ THE POSITIVE CONTROL, AND WITHOUT IT EVERY ASSERTION ABOVE PASSES
     * AGAINST A PLATFORM THAT RELEASES NOTHING TO ANYBODY.
     *
     * That failure is silent, looks exactly like success, and its first symptom
     * is two people who liked each other never hearing anything. So this proves
     * the gate still OPENS for somebody entitled to pass through it.
     */
    public function test_a_verified_approving_contact_does_release_what_she_is_entitled_to(): void
    {
        $her = $this->member('woman', 'man', 'aisha@example.com');
        $him = $this->member('man', 'woman', 'omar@example.com');

        [$herRow] = $this->mutualRows($her, $him);

        $guardian = Guardian::create([
            'member_id' => $her->user_id,
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'phone' => '+18765551111',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
        ]);

        foreach ([Guardian::GATE_POOL, Guardian::GATE_INTRODUCTION, Guardian::GATE_CONTACT] as $gate) {
            GuardianDecision::create([
                'guardian_id' => $guardian->id,
                'match_id' => $herRow->id,
                'gate' => $gate,
                'decision' => GuardianDecision::APPROVED,
                'decided_at' => now(),
            ]);
        }

        $payload = app(MatchPresenter::class)->full($herRow->fresh());

        /*
         * ⚠️ SHE IS MEDIATED, SO WHAT SHE IS ENTITLED TO IS THE INTRODUCTION
         * BEING MADE, NOT THE PHONE NUMBER. The scholar's ruling stands on top
         * of this gate: her trusted contact carries it.
         */
        $this->assertTrue(
            $payload['introductionIsBeingMade'],
            'With a verified, approving trusted contact in place nothing happens at all. The '
                .'gate is refusing everybody, so the assertions above are measuring a dead '
                .'product rather than a working rule.',
        );
    }
}
