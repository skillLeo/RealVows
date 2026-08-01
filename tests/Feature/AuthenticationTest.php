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

        $response->assertRedirect('/verify-email');

        $user = User::where('email', 'ayesha@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('member'));

        // The profile exists from the first moment, so the builder always has
        // a row to autosave into.
        $this->assertNotNull($user->profile);
        $this->assertInstanceOf(Profile::class, $user->profile);
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
