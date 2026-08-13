<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use App\Support\GuardianPresenter;
use App\Support\MatchPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE TRUSTED CONTACT MAKES THE INTRODUCTION. HE DOES NOT MERELY PERMIT IT.
 *
 * ── The ruling, and what it changed ───────────────────────────────────────
 *
 * The client's scholar was asked whether a guardian APPROVING an exchange is
 * the same as a guardian being PRESENT for it. The product had assumed it was:
 * he approved at the third gate, the surname and phone number appeared on the
 * member's own screen, and the two of them spoke privately with nobody else
 * involved. He gated the meeting and did not attend it.
 *
 * The ruling: not the same, except where the guardian is unjust, is not
 * Muslim, or has died. Outside those, he must be present.
 *
 * ⚠️ THOSE EXCEPTIONS ARE WHAT `is_appointed` ALREADY MEANS. A member reaches
 * an appointed contact precisely when no family guardian can stand for her. So
 * the exception the ruling carves out was already a flag in this system, and
 * the whole change is which of two people receives a phone number.
 *
 * ── ⚠️ WHY BOTH HALVES ARE TESTED TOGETHER ───────────────────────────────
 *
 * Withholding the details from the member is half a feature. On its own it
 * leaves the introduction dead at the last gate: she cannot proceed, he has
 * nothing to pass on, and the product simply stops with nobody holding
 * anything and no error to show for it.
 *
 * A test file that only asserted the withholding would call that a pass. So
 * every case here checks BOTH payloads, and the file fails if the details
 * reach neither of them.
 */
class TrustedContactMakesTheIntroductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        app(SettingsService::class)->set(
            GuardianPolicy::GROUP,
            GuardianPolicy::KEY,
            GuardianPolicy::REQUIRED_FEMALE,
        );
        app(SettingsService::class)->flush();
    }

    /** @return array{0: MatchRecord, 1: Guardian, 2: Profile} */
    private function mutualIntroduction(bool $appointed = false, bool $withContact = true): array
    {
        $me = User::factory()->create();
        $me->assignRole('member');
        $mine = Profile::factory()->woman()->create(['user_id' => $me->id, 'submitted_at' => now()]);

        $himUser = User::factory()->create([
            'email' => 'omar@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '8765550123',
        ]);
        $him = Profile::factory()->man()->create([
            'user_id' => $himUser->id,
            'last_name' => 'Adeyemi',
            'submitted_at' => now(),
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $him->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => MatchRecord::RESPONSE_INTERESTED,
        ]);

        // Mutual interest is derived from the reciprocal row, never a flag.
        MatchRecord::create([
            'seeker_profile_id' => $him->id,
            'matched_profile_id' => $mine->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => MatchRecord::RESPONSE_INTERESTED,
        ]);

        $guardian = null;

        if ($withContact) {
            $guardian = Guardian::create([
                'member_id' => $me->id,
                'user_id' => User::factory()->create()->id,
                'name' => $appointed ? 'Appointed by RealVows' : 'Yusuf Adeyemi',
                'relationship' => $appointed ? 'appointed' : 'father',
                'status' => Guardian::STATUS_VERIFIED,
                'is_appointed' => $appointed,
                'verified_at' => now(),
            ]);

            foreach ([Guardian::GATE_POOL, Guardian::GATE_INTRODUCTION, Guardian::GATE_CONTACT] as $gate) {
                GuardianDecision::create([
                    'guardian_id' => $guardian->id,
                    'match_id' => $gate === Guardian::GATE_POOL ? null : $match->id,
                    'gate' => $gate,
                    'decision' => 'approved',
                    'decided_at' => now(),
                ]);
            }
        }

        return [$match->fresh(), $guardian, $him->fresh()];
    }

    // ── The family contact: he is the channel ────────────────────────────

    public function test_a_member_with_a_family_contact_never_receives_the_details(): void
    {
        [$match] = $this->mutualIntroduction();

        $payload = app(MatchPresenter::class)->full($match);

        $this->assertArrayNotHasKey('contact', $payload, 'The phone number reached the member.');
        $this->assertArrayNotHasKey('lastName', $payload, 'The surname reached the member. A surname and a city is enough to find somebody.');

        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('8765550123', $encoded);
        $this->assertStringNotContainsString('omar@example.com', $encoded);
    }

    public function test_she_is_told_why_rather_than_left_at_a_dead_end(): void
    {
        [$match] = $this->mutualIntroduction();

        $payload = app(MatchPresenter::class)->full($match);

        $this->assertTrue($payload['contactWithTrustedContact']);
        $this->assertStringContainsString(
            'will make the introduction',
            $payload['contactWithTrustedContactReason'],
        );
    }

    public function test_the_details_reach_the_trusted_contact_instead(): void
    {
        /*
         * ⚠️ THE HALF THAT MAKES THE OTHER HALF A FEATURE RATHER THAN A DEAD
         * END. Without this the introduction stops at the final gate with
         * nobody holding anything.
         */
        [$match, $guardian, $him] = $this->mutualIntroduction();

        $payload = app(GuardianPresenter::class)->introduction($guardian, $match, $him);

        $this->assertTrue($payload['contactIsYoursToPass']);
        $this->assertSame('Adeyemi', $payload['contact']['lastName']);
        $this->assertSame('omar@example.com', $payload['contact']['email']);
        $this->assertStringContainsString('8765550123', $payload['contact']['phone']);
    }

    public function test_he_holds_nothing_until_he_has_approved_the_exchange(): void
    {
        /*
         * ⚠️ OTHERWISE HIS APPROVAL IS A FORMALITY HE CAN SKIP. A contact who
         * could read the phone number by opening the page has already been
         * given what approving was supposed to release, which is the exact
         * failure this ruling corrects.
         */
        [$match, $guardian, $him] = $this->mutualIntroduction();

        GuardianDecision::where('guardian_id', $guardian->id)
            ->where('gate', Guardian::GATE_CONTACT)
            ->delete();

        $payload = app(GuardianPresenter::class)->introduction($guardian->fresh(), $match, $him);

        $this->assertArrayNotHasKey('contact', $payload);
        $this->assertStringNotContainsString('8765550123', json_encode($payload));
    }

    // ── The exceptions the ruling names ──────────────────────────────────

    public function test_an_appointed_contact_does_not_mediate_and_she_receives_them(): void
    {
        /*
         * ⚠️ THE EXCEPTION, AND IT IS THE POSITIVE CONTROL FOR THE WHOLE FILE.
         *
         * The ruling carved out the guardian who is unjust, not Muslim, or
         * dead, which is exactly when a member is given an appointed contact.
         * Without this case, every assertion above would pass against a product
         * that had simply stopped releasing contact details to anybody.
         */
        [$match] = $this->mutualIntroduction(appointed: true);

        $payload = app(MatchPresenter::class)->full($match);

        $this->assertArrayNotHasKey('contactWithTrustedContact', $payload);
        $this->assertSame('Adeyemi', $payload['lastName']);
        $this->assertSame('omar@example.com', $payload['contact']['email']);
    }

    public function test_an_appointed_contact_is_never_handed_them_to_pass_on(): void
    {
        /*
         * He is a stranger the platform placed in that seat. Handing him a
         * woman's phone number so he could pass it on would be a worse exposure
         * than the one the rule exists to prevent.
         */
        [$match, $guardian, $him] = $this->mutualIntroduction(appointed: true);

        $payload = app(GuardianPresenter::class)->introduction($guardian, $match, $him);

        $this->assertArrayNotHasKey('contact', $payload);
        $this->assertStringNotContainsString('8765550123', json_encode($payload));
    }

    public function test_a_member_who_needs_no_contact_is_unaffected(): void
    {
        /*
         * The second control. A man on `required_female` has no trusted contact
         * and nothing about this ruling touches him. If mediation applied to
         * everybody, contact exchange would be broken for half the platform.
         */
        $him = User::factory()->create();
        $him->assignRole('member');
        $his = Profile::factory()->man()->create(['user_id' => $him->id, 'submitted_at' => now()]);

        $herUser = User::factory()->create(['email' => 'aisha@example.com']);
        $hers = Profile::factory()->woman()->create([
            'user_id' => $herUser->id,
            'last_name' => 'Bakare',
            'submitted_at' => now(),
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $his->id,
            'matched_profile_id' => $hers->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => MatchRecord::RESPONSE_INTERESTED,
        ]);

        MatchRecord::create([
            'seeker_profile_id' => $hers->id,
            'matched_profile_id' => $his->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => MatchRecord::RESPONSE_INTERESTED,
        ]);

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertSame('Bakare', $payload['lastName']);
        $this->assertSame('aisha@example.com', $payload['contact']['email']);
    }

    public function test_one_sided_interest_still_releases_nothing_to_anybody(): void
    {
        // The earlier gates have not stopped applying because a later one was
        // added. Mediation is a fourth condition, never a replacement.
        [$match, $guardian, $him] = $this->mutualIntroduction();

        MatchRecord::where('seeker_profile_id', $match->matched_profile_id)
            ->where('matched_profile_id', $match->seeker_profile_id)
            ->update(['response' => null]);

        $member = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayNotHasKey('contact', $member);
        $this->assertArrayNotHasKey(
            'contactWithTrustedContact',
            $member,
            'She was told her trusted contact holds the details before the other person had '
                .'even answered.',
        );

        // He is told nothing either, since there is nothing yet to pass on.
        $contact = app(GuardianPresenter::class)->introduction($guardian, $match->fresh(), $him);
        $this->assertArrayNotHasKey('contact', $contact);
    }
}
