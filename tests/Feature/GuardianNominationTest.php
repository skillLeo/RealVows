<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianInvitation;
use App\Models\Profile;
use App\Models\User;
use App\Services\Guardian\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Naming a trusted contact, and the checks that run before anybody is messaged.
 */
class GuardianNominationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function member(string $email = 'aisha@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'phone_country_code' => '+1',
            'phone_number' => '8761234567',
        ]);

        Profile::factory()->woman()->create([
            'user_id' => $user->id,
            'date_of_birth' => '1996-04-01',
        ]);

        return $user->fresh();
    }

    public function test_a_plausible_nomination_creates_a_guardian_and_one_live_invitation(): void
    {
        $result = app(GuardianService::class)->nominate($this->member(), [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'phone' => '+1 876 555 0000',
            'email' => 'yusuf@example.com',
            'date_of_birth' => '1968-02-11',
        ]);

        $this->assertSame([], $result['rejections']);
        $this->assertNotNull($result['guardian']);
        $this->assertNotNull($result['token']);
        $this->assertSame(Guardian::STATUS_INVITED, $result['guardian']->status);

        // ⚠️ The plaintext token is returned once and never stored.
        $this->assertDatabaseMissing('guardian_invitations', ['token_hash' => $result['token']]);
        $this->assertNotNull(GuardianInvitation::findByToken($result['token']));
    }

    /**
     * ⚠️ THE HIGHEST-SIGNAL FRAUD CASE. One person holding both sides of a
     * check designed to involve two.
     */
    public function test_a_guardian_phone_belonging_to_a_member_is_rejected_before_anything_is_sent(): void
    {
        $member = $this->member();

        // Somebody else's account already uses this number.
        User::factory()->create(['phone_country_code' => '+1', 'phone_number' => '8769999999']);

        $result = app(GuardianService::class)->nominate($member, [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'phone' => '+1 876 999 9999',
        ]);

        $this->assertNotEmpty($result['rejections']);
        $this->assertNull($result['guardian'], 'A guardian row was created despite a rejection.');
        $this->assertNull($result['token'], 'An invitation was issued despite a rejection.');
        $this->assertDatabaseCount('guardians', 0);
    }

    /**
     * ⚠️ PLUS-ALIASES AND DOT-VARIANTS DELIVER TO THE MEMBER'S OWN INBOX.
     * She would be approving her own introductions while the system believed
     * two people were involved.
     */
    public function test_an_alias_of_the_members_own_email_is_rejected(): void
    {
        $member = $this->member('aisha@gmail.com');

        foreach (['aisha+dad@gmail.com', 'a.i.s.h.a@gmail.com'] as $alias) {
            $result = app(GuardianService::class)->nominate($member, [
                'name' => 'Yusuf Adeyemi',
                'relationship' => 'father',
                'email' => $alias,
            ]);

            $this->assertNotEmpty($result['rejections'], "[{$alias}] was accepted.");
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every rejection above would also pass against a
     * service that refused every nomination. This proves an ordinary different
     * address gets through.
     */
    public function test_an_ordinary_different_address_is_accepted(): void
    {
        $result = app(GuardianService::class)->nominate($this->member('aisha@gmail.com'), [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf.adeyemi@gmail.com',
        ]);

        $this->assertSame(
            [],
            $result['rejections'],
            'An ordinary nomination was rejected, so the refusals above prove nothing.'
        );
    }

    /** ⚠️ A flag lets the member continue and tells the reviewer where to look. */
    public function test_an_impossible_age_gap_flags_rather_than_rejects(): void
    {
        $result = app(GuardianService::class)->nominate($this->member(), [
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'date_of_birth' => '1994-01-01', // two years older than the member
        ]);

        $this->assertSame([], $result['rejections'], 'An age gap should flag, never reject.');
        $this->assertNotEmpty($result['flags']);
        $this->assertNotNull($result['guardian']);
    }

    public function test_re_nominating_replaces_rather_than_deletes(): void
    {
        $member = $this->member();
        $service = app(GuardianService::class);

        $first = $service->nominate($member, [
            'name' => 'Yusuf Adeyemi', 'relationship' => 'father', 'email' => 'yusuf@example.com',
        ])['guardian'];

        $service->nominate($member, [
            'name' => 'Kemar Adeyemi', 'relationship' => 'brother', 'email' => 'kemar@example.com',
        ]);

        $this->assertSame(
            Guardian::STATUS_REPLACED,
            $first->fresh()->status,
            'The earlier nomination was deleted rather than marked replaced. Its decision '
                .'history is the evidence for why somebody was replaced.'
        );
        $this->assertDatabaseCount('guardians', 2);
    }

    /**
     * ⚠️ THE NO-GUARDIAN PATH (B7) MARKS is_appointed, WHICH IS WHAT TRIGGERS
     * THE PHOTOGRAPH RULE.
     */
    public function test_the_no_contact_path_creates_an_appointed_record(): void
    {
        $guardian = app(GuardianService::class)->requestAppointed($this->member());

        $this->assertTrue($guardian->is_appointed);
        $this->assertFalse($guardian->maySeePhotographs());
    }

    public function test_accepting_an_invitation_creates_the_guardians_own_account(): void
    {
        $member = $this->member();
        $service = app(GuardianService::class);

        $token = $service->nominate($member, [
            'name' => 'Yusuf Adeyemi', 'relationship' => 'father', 'email' => 'yusuf@example.com',
        ])['token'];

        $guardian = $service->accept($token, 'Yusuf Adeyemi', 'yusuf@example.com', 'a-real-password-1');

        $this->assertNotNull($guardian);
        $this->assertNotNull($guardian->user_id);
        $this->assertTrue($guardian->user->hasRole('guardian'));
        $this->assertSame(Guardian::STATUS_REGISTERED, $guardian->status);

        // ⚠️ Single use. The same link must not create a second account.
        $this->assertNull(
            $service->accept($token, 'Someone Else', 'else@example.com', 'another-password-1'),
            'The invitation token worked twice.'
        );
    }

    public function test_a_wrong_or_expired_token_finds_nothing(): void
    {
        $this->assertNull(
            app(GuardianService::class)->accept('not-a-real-token', 'X', 'x@example.com', 'password-1'),
        );
    }
}
