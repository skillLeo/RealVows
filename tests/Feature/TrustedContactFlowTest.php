<?php

namespace Tests\Feature;

use App\Mail\TrustedContactInvitation;
use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The member-facing flow, end to end over HTTP.
 */
class TrustedContactFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Mail::fake();
    }

    private function setLevel(string $level): void
    {
        app(SettingsService::class)->set(GuardianPolicy::GROUP, GuardianPolicy::KEY, $level);
    }

    private function member(): User
    {
        $user = User::factory()->create(['email' => 'aisha@example.com']);
        Profile::factory()->woman()->create(['user_id' => $user->id, 'date_of_birth' => '1996-04-01']);

        return $user->fresh();
    }

    /**
     * ⚠️ THE FEATURE IS OFF BY DEFAULT AND MUST BE INVISIBLE WHILE IT IS.
     *
     * A member who finds this URL today should find nothing, not a page
     * explaining a system the client has not switched on.
     */
    public function test_the_screen_is_absent_while_the_requirement_is_off(): void
    {
        $this->setLevel(GuardianPolicy::OFF);

        $this->actingAs($this->member())->get('/trusted-contact')->assertNotFound();
    }

    /** ⚠️ POSITIVE CONTROL: it appears once the feature is offered. */
    public function test_the_screen_appears_once_the_feature_is_offered(): void
    {
        $this->setLevel(GuardianPolicy::OPTIONAL);

        $this->actingAs($this->member())->get('/trusted-contact')->assertOk();
    }

    public function test_naming_a_contact_sends_one_invitation_email(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $this->actingAs($this->member())->post('/trusted-contact', [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'phone' => '+1 876 555 0000',
        ])->assertRedirect();

        Mail::assertSent(TrustedContactInvitation::class, 1);
        $this->assertDatabaseHas('guardians', ['email' => 'yusuf@example.com', 'is_appointed' => false]);
    }

    /**
     * ⚠️ NOTHING IS SENT WHEN THE DETAILS ARE REFUSED. The check exists to run
     * BEFORE a real person is messaged.
     */
    public function test_a_refused_nomination_sends_nothing_and_creates_nothing(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);
        $member = $this->member();

        $this->actingAs($member)->post('/trusted-contact', [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'aisha+dad@example.com', // delivers to her own inbox
        ])->assertSessionHasErrors('email');

        /*
         * ⚠️ SENT *AND* QUEUED. Queued mail is never "sent", so
         * assertNothingSent alone passes while an email is on its way — which
         * is exactly the failure this asserts against, since the whole point of
         * the check is that nobody is messaged before the details are accepted.
         */
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('guardians', 0);
    }

    /**
     * ⚠️ `appointed` IS NOT IN THE DROPDOWN. It is set by staff on the
     * no-contact path, and a member who could choose it would be choosing to
     * have photographs withheld from a family member.
     */
    public function test_a_member_cannot_nominate_somebody_as_appointed(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $this->actingAs($this->member())->post('/trusted-contact', [
            'name' => 'Anybody',
            'relationship' => 'appointed',
            'email' => 'anybody@example.com',
        ])->assertSessionHasErrors('relationship');

        $this->assertDatabaseCount('guardians', 0);
    }

    public function test_the_no_contact_path_records_an_appointed_case(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);

        $this->actingAs($this->member())->post('/trusted-contact/appointed')->assertRedirect();

        $this->assertDatabaseHas('guardians', ['is_appointed' => true, 'relationship' => 'appointed']);

        // Nobody to invite on this path, so nothing may leave by either route.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_an_invitation_link_opens_and_creates_the_contacts_own_account(): void
    {
        $this->setLevel(GuardianPolicy::REQUIRED_FEMALE);
        $member = $this->member();

        $result = app(\App\Services\Guardian\GuardianService::class)->nominate($member, [
            'name' => 'Yusuf Adeyemi', 'relationship' => 'father', 'email' => 'yusuf@example.com',
        ]);

        $token = $result['token'];

        $this->get("/trusted-contact/accept/{$token}")->assertOk();

        $this->post("/trusted-contact/accept/{$token}", [
            'name' => 'Yusuf Adeyemi',
            'email' => 'yusuf@example.com',
            'password' => 'a-real-password-1',
            'password_confirmation' => 'a-real-password-1',
        ])->assertRedirect(route('guardian.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('guardians', ['status' => Guardian::STATUS_REGISTERED]);
    }

    /**
     * ⚠️ WRONG, USED AND EXPIRED ALL 404. Distinguishing them tells a guesser
     * they found a real token that happened to be spent.
     */
    public function test_a_wrong_or_used_token_is_indistinguishable_from_nothing(): void
    {
        $this->get('/trusted-contact/accept/not-a-real-token')->assertNotFound();

        $member = $this->member();
        $token = app(\App\Services\Guardian\GuardianService::class)->nominate($member, [
            'name' => 'Yusuf', 'relationship' => 'father', 'email' => 'yusuf@example.com',
        ])['token'];

        $this->post("/trusted-contact/accept/{$token}", [
            'name' => 'Yusuf Adeyemi',
            'email' => 'yusuf@example.com',
            'password' => 'a-real-password-1',
            'password_confirmation' => 'a-real-password-1',
        ])->assertRedirect();

        // Used once. The same link is now nothing.
        $this->get("/trusted-contact/accept/{$token}")->assertNotFound();
    }
}
