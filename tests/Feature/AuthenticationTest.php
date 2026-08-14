<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_a_visitor_can_register_and_gets_a_profile_row(): void
    {
        $response = $this->post('/register', [
            'email' => 'ayesha@example.com',
            'password' => 'marriage9',
            'password_confirmation' => 'marriage9',
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
            'is_adult' => true,
            'accepts_terms' => true,
        ]);

        /*
         * ⚠️ STRAIGHT INTO THE BUILDER, NOT TO AN INBOX.
         *
         * This asserted `/verify-email`, which was correct until the client
         * pointed out what it costs: "if someone has to go on their email first
         * to confirm their email, then they might not bother with their
         * profile." Somebody who has typed a password and nothing else has
         * invested nothing, and sending them away at that moment is where they
         * stop.
         *
         * The email now sends when the profile is finished. Email verification
         * did not disappear — it moved from being a gate on the PRODUCT to a
         * gate on the POOL, enforced in Profile::scopeMatchable.
         */
        $response->assertRedirect('/profile');

        $user = User::where('email', 'ayesha@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('member'));

        // The profile exists from the first moment, so the builder always has
        // a row to autosave into.
        $this->assertNotNull($user->profile);
        $this->assertInstanceOf(Profile::class, $user->profile);
    }

    /**
     * ⚠️ NOTHING IS SENT AT REGISTRATION NOW, AND THAT IS THE WHOLE CHANGE.
     *
     * A test asserting the redirect alone would pass just as happily against a
     * version that still mailed them on the way past — the member would simply
     * find it waiting when they eventually looked, which is the behaviour the
     * client asked to remove.
     */
    public function test_no_verification_email_is_sent_at_registration(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->post('/register', [
            'email' => 'ayesha@example.com',
            'password' => 'marriage9',
            'password_confirmation' => 'marriage9',
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
            'is_adult' => true,
            'accepts_terms' => true,
        ]);

        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // And they really are unverified, so the pool gate below has work to do.
        $this->assertNull(User::where('email', 'ayesha@example.com')->first()->email_verified_at);
    }

    public function test_registration_requires_the_18_declaration_and_the_legal_acceptance(): void
    {
        $this->post('/register', [
            'email' => 'nope@example.com',
            'password' => 'marriage9',
            'password_confirmation' => 'marriage9',
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
            'is_adult' => false,
            'accepts_terms' => false,
        ])->assertSessionHasErrors(['is_adult', 'accepts_terms']);

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
    }

    public function test_a_weak_password_is_rejected_with_the_designed_message(): void
    {
        $this->post('/register', [
            'email' => 'weak@example.com',
            'password' => 'marriage',
            'password_confirmation' => 'marriage',
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
            'is_adult' => true,
            'accepts_terms' => true,
        ])->assertSessionHasErrors('password');

        $this->assertSame(
            'Password needs at least 8 characters, including one number.',
            session('errors')->first('password')
        );
    }

    public function test_a_member_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('marriage9'),
        ]);
        $user->assignRole('member');

        $this->post('/login', [
            'email' => 'member@example.com',
            'password' => 'marriage9',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_wrong_password_does_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('marriage9'),
        ]);

        $this->post('/login', ['email' => 'member@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors('email');

        $knownAccount = session('errors')->first('email');

        $this->flushSession();

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors('email');

        // Identical wording either way: a different message would confirm who
        // holds an account on a marriage platform.
        $this->assertSame($knownAccount, session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_closed_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'closed@example.com',
            'password' => Hash::make('marriage9'),
            'is_active' => false,
        ]);

        $this->post('/login', ['email' => 'closed@example.com', 'password' => 'marriage9'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('marriage9'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'member@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'member@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
