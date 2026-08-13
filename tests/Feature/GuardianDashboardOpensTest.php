<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trusted contact's dashboard must actually render.
 *
 * ⚠️ IT NEVER HAD, AND EVERY TEST WAS GREEN.
 *
 * The controller eager-loaded `matchedProfile.photos` and read
 * `$match->matchedProfile`. MatchRecord declares `seeker()` and `matched()` —
 * there has never been a relation by that name. So the one screen where
 * introductions are approved or declined threw RelationNotFoundException on
 * first render, for its entire existence.
 *
 * Nothing caught it, and the reason is worth writing down. GuardianPhotoRuleTest
 * exercises GuardianPresenter directly and proves the photograph rule holds —
 * correct, valuable, and completely silent about whether the page it feeds can
 * load. The demo had no guardian accounts, so nobody could click it either.
 *
 * A unit test on the thing a page uses is not a test of the page. This opens
 * the route.
 */
class GuardianDashboardOpensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /** @return array{0: User, 1: Profile} */
    private function wardWithGuardian(bool $appointed = false): array
    {
        $member = User::factory()->create();
        $ward = Profile::factory()->create(['user_id' => $member->id, 'gender' => 'woman', 'first_name' => 'Aisha']);

        $contact = User::factory()->create(['email' => 'guardian@realvows.test']);

        Guardian::create([
            'member_id' => $member->id,
            'user_id' => $contact->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => $appointed,
            'verified_at' => now(),
        ]);

        return [$contact, $ward];
    }

    private function introductionFor(Profile $ward): MatchRecord
    {
        $other = Profile::factory()->create(['user_id' => User::factory()->create()->id, 'gender' => 'man']);

        return MatchRecord::create([
            'seeker_profile_id' => $ward->id,
            'matched_profile_id' => $other->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_TEASER,
        ]);
    }

    public function test_the_dashboard_renders_for_a_trusted_contact(): void
    {
        [$contact, $ward] = $this->wardWithGuardian();
        $this->introductionFor($ward);

        $response = $this->actingAs($contact)->get('/trusted-contact-dashboard');

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        /*
         * ⚠️ ASSERT THE INTRODUCTION IS THERE, not merely that the page was
         * 200. A controller that silently returned an empty list would satisfy
         * assertOk and leave a trusted contact looking at "nothing waiting"
         * while an introduction sat unapproved.
         */
        $this->assertCount(1, $props['introductions']);
        $this->assertSame('Aisha', $props['ward']['firstName']);
    }

    public function test_it_renders_with_nothing_waiting_too(): void
    {
        // The other direction: an empty queue must not be an error either.
        [$contact] = $this->wardWithGuardian();

        $response = $this->actingAs($contact)->get('/trusted-contact-dashboard');

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['introductions']);
    }

    public function test_a_family_contact_receives_the_photographs(): void
    {
        /*
         * The control for the rule this screen exists to enforce. Without it,
         * a presenter that withheld photographs from everybody would pass the
         * appointed-contact assertion below and quietly break the family case.
         */
        [$contact, $ward] = $this->wardWithGuardian(appointed: false);
        $this->introductionFor($ward);

        $props = $this->actingAs($contact)->get('/trusted-contact-dashboard')->viewData('page')['props'];

        $this->assertArrayHasKey('photos', $props['introductions'][0]);
        $this->assertArrayNotHasKey('photographsWithheld', $props['introductions'][0]);
    }

    public function test_an_appointed_contact_does_not(): void
    {
        [$contact, $ward] = $this->wardWithGuardian(appointed: true);
        $this->introductionFor($ward);

        $props = $this->actingAs($contact)->get('/trusted-contact-dashboard')->viewData('page')['props'];

        $this->assertArrayNotHasKey('photos', $props['introductions'][0]);
        $this->assertTrue($props['introductions'][0]['photographsWithheld']);
    }

    public function test_a_member_cannot_open_the_trusted_contact_dashboard(): void
    {
        $member = User::factory()->create();
        Profile::factory()->create(['user_id' => $member->id]);

        $this->actingAs($member)->get('/trusted-contact-dashboard')->assertNotFound();
    }
}
