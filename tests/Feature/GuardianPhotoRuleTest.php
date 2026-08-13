<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Support\GuardianPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ AN APPOINTED TRUSTED CONTACT MUST NEVER RECEIVE A PHOTOGRAPH.
 *
 * This is the highest-stakes rule in the guardian system. Getting it wrong
 * does not degrade a feature — it inverts one. The system exists so a woman
 * with nobody to vouch for her is not left exposed, and the failure mode is
 * the platform itself handing her photographs to a stranger it appointed.
 *
 * A family guardian is a mahram and may see them. An appointed guardian is a
 * stranger and may not, in either direction: not the other party's, and not
 * his own ward's.
 *
 * ── ⚠️ WHY THE POSITIVE CONTROL IS NOT OPTIONAL HERE ──────────────────────
 *
 * Every assertion in this file is "no photo path appears in this payload". A
 * presenter that returned an empty array for everybody would satisfy all of
 * them, and so would a presenter that threw. The control asserts that a FAMILY
 * guardian DOES receive photographs through the same method, which is what
 * makes the absences above mean "withheld" rather than "this never works".
 */
class GuardianPhotoRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function scenario(bool $appointed): array
    {
        $memberUser = User::factory()->create();
        $ward = Profile::factory()->woman()->create(['user_id' => $memberUser->id]);
        $other = Profile::factory()->man()->create();

        foreach ([$ward, $other] as $profile) {
            Photo::create([
                'profile_id' => $profile->id,
                'slot' => 'passport',
                'path' => 'profiles/'.$profile->id.'/private-portrait.jpg',
                'webp_path' => 'profiles/'.$profile->id.'/private-portrait.webp',
                'thumbnail_path' => 'profiles/'.$profile->id.'/private-portrait-thumb.jpg',
                'original_filename' => 'portrait.jpg',
                'mime' => 'image/jpeg',
                'size_bytes' => 2048,
                'width' => 1200,
                'height' => 1600,
            ]);
        }

        $guardian = Guardian::create([
            'member_id' => $memberUser->id,
            'name' => $appointed ? 'Appointed by RealVows' : 'Yusuf Adeyemi',
            'relationship' => $appointed ? 'appointed' : 'father',
            'is_appointed' => $appointed,
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $ward->id,
            'matched_profile_id' => $other->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => ['values' => ['raw' => 92.0]]],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => ['You both put family first in your values.'],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        return [$guardian, $match, $ward->fresh()->load('photos'), $other->fresh()->load('photos')];
    }

    public function test_an_appointed_trusted_contact_receives_no_photograph_of_the_other_party(): void
    {
        [$guardian, $match, , $other] = $this->scenario(appointed: true);

        $payload = json_encode(app(GuardianPresenter::class)->introduction($guardian, $match, $other));

        $this->assertSame(
            [],
            Scanners::photoPaths($payload),
            'An appointed trusted contact was sent a photograph path. This is the exposure the '
                .'whole system exists to prevent.'
        );

        $this->assertStringNotContainsString('private-portrait', $payload);
        $this->assertStringContainsString('photographsWithheld', $payload);
    }

    public function test_an_appointed_trusted_contact_receives_no_photograph_of_his_own_ward(): void
    {
        [$guardian, , $ward] = $this->scenario(appointed: true);

        $payload = json_encode(app(GuardianPresenter::class)->ward($guardian, $ward));

        $this->assertSame([], Scanners::photoPaths($payload));
        $this->assertStringNotContainsString('private-portrait', $payload);
    }

    public function test_an_appointed_trusted_contact_cannot_stream_a_photograph(): void
    {
        [$guardian, , $ward, $other] = $this->scenario(appointed: true);

        $presenter = app(GuardianPresenter::class);

        // Neither the other party's nor his own ward's. He is a stranger to both.
        $this->assertFalse($presenter->mayStreamPhotographOf($guardian, $other));
        $this->assertFalse($presenter->mayStreamPhotographOf($guardian, $ward));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Without this, every assertion above would
     * pass against a presenter that returned nothing to anybody.
     */
    public function test_a_family_trusted_contact_does_receive_photographs(): void
    {
        [$guardian, $match, , $other] = $this->scenario(appointed: false);

        $payload = json_encode(app(GuardianPresenter::class)->introduction($guardian, $match, $other));

        $this->assertStringContainsString(
            'photos',
            $payload,
            'A family trusted contact received no photographs, so the refusals asserted above '
                .'prove nothing about the appointed case.'
        );

        $decoded = json_decode($payload, true);
        $this->assertNotEmpty($decoded['photos'], 'The photos array is present but empty.');
        $this->assertArrayNotHasKey('photographsWithheld', $decoded);

        $this->assertTrue(app(GuardianPresenter::class)->mayStreamPhotographOf($guardian, $other));
    }

    /**
     * ⚠️ THE RULE IS THE FLAG, NOT THE RELATIONSHIP STRING.
     *
     * A row with `relationship = 'father'` and `is_appointed = true` is
     * contradictory, and the safe reading of a contradiction is the restrictive
     * one. Somebody could otherwise flip the label and defeat the rule.
     */
    public function test_the_appointed_flag_wins_over_a_family_relationship_label(): void
    {
        [$guardian, $match, , $other] = $this->scenario(appointed: false);

        $guardian->forceFill(['is_appointed' => true])->save();

        $payload = json_encode(
            app(GuardianPresenter::class)->introduction($guardian->fresh(), $match, $other)
        );

        $this->assertSame([], Scanners::photoPaths($payload));
        $this->assertStringNotContainsString('private-portrait', $payload);
    }

    /**
     * ⚠️ THE MIRRORED SCENARIO, WHICH NOTHING IN THIS FILE USED TO BUILD.
     *
     * Every case above puts a WOMAN in the ward's seat, so the other party is
     * always a man and the leak is invisible: a father seeing his daughter's
     * suitor is the case the feature was designed around, and it is correct.
     *
     * Men may name a trusted contact too. Turn the scenario around — a man's
     * father opening his son's introduction — and the other party is a WOMAN.
     * The presenter handed over her photographs, because it asked only whether
     * the contact was appointed and never whose pictures these were.
     *
     * ⚠️ THAT IS THE EXACT EXPOSURE THE GUARDIAN SYSTEM EXISTS TO PREVENT,
     * PRODUCED BY THE GUARDIAN SYSTEM. Her photographs reached a man she has no
     * relationship to, has never met, and never agreed to be seen by.
     *
     * @return array{0: Guardian, 1: MatchRecord, 2: Profile, 3: Profile}
     */
    private function mirroredScenario(): array
    {
        $memberUser = User::factory()->create();
        $ward = Profile::factory()->man()->create(['user_id' => $memberUser->id]);
        $other = Profile::factory()->woman()->create();

        foreach ([$ward, $other] as $profile) {
            Photo::create([
                'profile_id' => $profile->id,
                'slot' => 'passport',
                'path' => 'profiles/'.$profile->id.'/private-portrait.jpg',
                'webp_path' => 'profiles/'.$profile->id.'/private-portrait.webp',
                'thumbnail_path' => 'profiles/'.$profile->id.'/private-portrait-thumb.jpg',
                'original_filename' => 'portrait.jpg',
                'mime' => 'image/jpeg',
                'size_bytes' => 2048,
                'width' => 1200,
                'height' => 1600,
            ]);
        }

        $guardian = Guardian::create([
            'member_id' => $memberUser->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'is_appointed' => false,
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $ward->id,
            'matched_profile_id' => $other->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        return [$guardian, $match, $ward->fresh()->load('photos'), $other->fresh()->load('photos')];
    }

    public function test_a_mans_family_contact_never_receives_the_womans_photographs(): void
    {
        [$guardian, $match, , $her] = $this->mirroredScenario();

        $payload = json_encode(app(GuardianPresenter::class)->introduction($guardian, $match, $her));

        $this->assertSame(
            [],
            Scanners::photoPaths($payload),
            'A woman\'s photographs were sent to the trusted contact of the man she was '
                .'matched with. He is not related to her, and this is the exposure the whole '
                .'system exists to prevent.'
        );

        $this->assertStringNotContainsString('private-portrait', $payload);
        $this->assertStringContainsString('photographsWithheld', $payload);
    }

    public function test_the_bytes_are_refused_to_him_as_well(): void
    {
        /*
         * ⚠️ THE SECOND DOOR. Her profile IS reachable from his ward's match
         * rows, so the controller's reachability check passes and always did.
         * Reachability answers "is this connected to him", never "is this his
         * to see". If only the payload had changed, this would still serve.
         */
        [$guardian, , , $her] = $this->mirroredScenario();

        $this->assertFalse(
            app(GuardianPresenter::class)->mayStreamPhotographOf($guardian, $her),
        );
    }

    public function test_but_he_still_sees_his_own_sons_photographs(): void
    {
        /*
         * ⚠️ THE CONTROL FOR THE MIRRORED CASE. Both assertions above are
         * absences, and a rule of "this guardian gets nothing" would satisfy
         * them while breaking the father who is meant to be advising.
         */
        [$guardian, , $ward] = $this->mirroredScenario();

        $payload = app(GuardianPresenter::class)->ward($guardian, $ward);

        $this->assertNotEmpty($payload['photos'] ?? []);
        $this->assertArrayNotHasKey('photographsWithheld', $payload);
    }

    public function test_and_the_withholding_says_which_rule_it_is(): void
    {
        /*
         * The two withholdings are different facts and must not share a
         * sentence. Telling a father "not shared with an appointed contact"
         * about his own son's introduction would be false and would read as a
         * fault in the page rather than as a rule.
         */
        [$guardian, $match, , $her] = $this->mirroredScenario();

        $payload = app(GuardianPresenter::class)->introduction($guardian, $match, $her);

        $this->assertStringContainsString(
            'only with her own trusted contact',
            $payload['photographsWithheldReason'],
        );
        $this->assertStringNotContainsString('appointed', $payload['photographsWithheldReason']);
    }
}
