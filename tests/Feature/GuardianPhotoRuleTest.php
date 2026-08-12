<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Support\GuardianPresenter;
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
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
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
        [$guardian] = $this->scenario(appointed: true);

        $this->assertFalse(app(GuardianPresenter::class)->mayStreamPhotograph($guardian));
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

        $this->assertTrue(app(GuardianPresenter::class)->mayStreamPhotograph($guardian));
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
}
