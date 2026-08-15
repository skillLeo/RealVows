<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Guardian\GuardianGate;
use App\Support\MatchPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE RULE HELD ON HER SCREEN AND WAS DEFEATED ON HIS.
 *
 * The scholar: "arranging conversation between the two is not permitted in
 * Shariah. Whatever necessary matters need to be satisfied should be handled
 * through the women of the household."
 *
 * Everything built for that rule was asked about one person: the seeker of
 * whichever match row was being read. A match is two rows, one per member, so
 * on the man's row the question became "does the MAN have a trusted contact" —
 * and under `required_female` he never does.
 *
 * ⚠️ SO HE RECEIVED HER SURNAME, EMAIL AND PHONE. He could call her, and the
 * two were talking. The prohibition arrived from the other direction, through
 * the half of the pair nobody was asking about.
 *
 * ⚠️ AND IT DID NOT WAIT FOR HER TRUSTED CONTACT. Her guardian's decisions are
 * recorded against HER row, so his row never consulted them: the details moved
 * on mutual interest alone, before anybody had approved the contact gate.
 *
 * Mediation and release are now properties of the PAIR.
 */
class NeitherSideReceivesTheOtherTest extends TestCase
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
            'last_name' => $gender === 'woman' ? 'Bakare' : 'Adeyemi',
            'submitted_at' => now(),
        ]);

        Verification::factory()->verified()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    /** Both rows, both interested, plus her contact and its approvals. */
    private function pairing(bool $appointed): array
    {
        $her = $this->member('woman', 'man', 'aisha@example.com');
        $him = $this->member('man', 'woman', 'omar@example.com');

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

        [$herRow, $hisRow] = $rows;

        // ⚠️ Every gate she needs, approved. The point of this file is what
        // happens AFTER everything legitimate has been granted.
        foreach ([Guardian::GATE_POOL, Guardian::GATE_INTRODUCTION, Guardian::GATE_CONTACT] as $gate) {
            GuardianDecision::create([
                'guardian_id' => $guardian->id,
                'match_id' => $herRow->id,
                'gate' => $gate,
                'decision' => GuardianDecision::APPROVED,
                'decided_at' => now(),
            ]);
        }

        return [$herRow->fresh(), $hisRow->fresh(), $guardian, $her, $him];
    }

    // ── A family trusted contact ─────────────────────────────────────────

    public function test_the_man_never_receives_her_details(): void
    {
        [, $hisRow] = $this->pairing(appointed: false);

        $payload = app(MatchPresenter::class)->full($hisRow);

        $this->assertArrayNotHasKey(
            'contact',
            $payload,
            'He received her phone number and email. He can call her, and the two are talking, '
                .'which is the prohibition arriving from the direction nobody was checking.',
        );
        $this->assertArrayNotHasKey('lastName', $payload, 'He received her surname.');

        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('aisha@example.com', $encoded);
        $this->assertStringNotContainsString('8765550123', $encoded);
        $this->assertStringNotContainsString('Bakare', $encoded);
    }

    public function test_she_never_receives_his_details(): void
    {
        [$herRow] = $this->pairing(appointed: false);

        $payload = app(MatchPresenter::class)->full($herRow);

        $this->assertArrayNotHasKey('contact', $payload);
        $this->assertArrayNotHasKey('lastName', $payload);
    }

    public function test_both_are_told_who_is_making_it_rather_than_left_at_a_dead_end(): void
    {
        [$herRow, $hisRow] = $this->pairing(appointed: false);

        foreach ([$herRow, $hisRow] as $row) {
            $payload = app(MatchPresenter::class)->full($row);

            $this->assertTrue(
                $payload['introductionIsBeingMade'],
                'Somebody was shown a finished introduction with no details and no explanation, '
                    .'which reads as a broken page rather than as the design.',
            );
            $this->assertSame(GuardianGate::MEDIATION_GUARDIAN, $payload['introductionIsBeingMadeBy']);
        }
    }

    // ── An appointed contact: staff carry it ─────────────────────────────

    public function test_an_appointed_contact_means_staff_and_she_still_receives_nothing(): void
    {
        [$herRow] = $this->pairing(appointed: true);

        $payload = app(MatchPresenter::class)->full($herRow);

        $this->assertArrayNotHasKey(
            'contact',
            $payload,
            'A woman with an appointed contact received his details on her own screen. She has '
                .'the least family and was getting the least protection.',
        );
        $this->assertSame(GuardianGate::MEDIATION_STAFF, $payload['introductionIsBeingMadeBy']);
    }

    public function test_the_man_receives_nothing_from_an_appointed_pairing_either(): void
    {
        [, $hisRow] = $this->pairing(appointed: true);

        $payload = app(MatchPresenter::class)->full($hisRow);

        $this->assertArrayNotHasKey('contact', $payload);
        $this->assertStringNotContainsString('aisha@example.com', json_encode($payload));
    }

    // ── ⚠️ The control, without which every assertion above is empty ─────

    /**
     * ⚠️ EVERY TEST IN THIS FILE ASSERTS AN ABSENCE, AND A PRODUCT THAT HAD
     * STOPPED RELEASING CONTACT DETAILS TO ANYBODY WOULD SATISFY ALL OF THEM.
     *
     * That failure is worse than the one being prevented: it is silent, it
     * looks exactly like success, and the first symptom is two people who liked
     * each other never hearing anything. So this proves the mechanism still
     * releases when nobody is mediating.
     */
    public function test_with_no_requirement_the_details_still_move(): void
    {
        \App\Models\Setting::where('group', 'guardian')
            ->where('key', 'guardian_requirement')
            ->update(['value' => \App\Support\GuardianPolicy::OFF]);

        app(\App\Services\Settings\SettingsService::class)->flush();

        $her = $this->member('woman', 'man', 'aisha@example.com');
        $him = $this->member('man', 'woman', 'omar@example.com');

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

        $payload = app(MatchPresenter::class)->full($rows[0]->fresh());

        $this->assertSame('Adeyemi', $payload['lastName']);
        $this->assertSame('omar@example.com', $payload['contact']['email']);
    }
}
