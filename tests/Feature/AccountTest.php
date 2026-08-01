<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('marriage9'),
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
            'phone_verified_at' => now(),
        ]);
        $user->assignRole('member');
        Profile::create(['user_id' => $user->id]);

        return $user;
    }

    public function test_changing_the_mobile_number_clears_its_verification(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/account/contact', [
            'phone_country_code' => '+1',
            'phone_number' => '876 9998888',
        ])->assertRedirect();

        $fresh = $user->fresh();

        $this->assertSame('876 9998888', $fresh->phone_number);

        // Carrying the old verification across would mean matches going to
        // whoever now owns the previous handset.
        $this->assertNull($fresh->phone_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.phone_changed']);
    }

    public function test_saving_the_same_number_leaves_its_verification_alone(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/account/contact', [
            'phone_country_code' => '+1',
            'phone_number' => '876 5551234',
        ])->assertRedirect();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_a_password_change_requires_the_current_password(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/account/password', [
            'current_password' => 'not-the-right-one',
            'password' => 'newpassword9',
            'password_confirmation' => 'newpassword9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('marriage9', $user->fresh()->password));
    }

    public function test_a_member_can_change_their_password(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/account/password', [
            'current_password' => 'marriage9',
            'password' => 'newpassword9',
            'password_confirmation' => 'newpassword9',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('newpassword9', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed']);
    }

    public function test_the_packages_page_is_honest_about_there_being_no_checkout_yet(): void
    {
        $this->actingAs($this->member())
            ->get('/packages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Packages'));
    }

    public function test_the_account_flag_that_closes_a_member_is_not_mass_assignable(): void
    {
        $user = $this->member();

        // A stray `is_active` in a request payload must never be able to
        // close or reopen an account.
        $user->update(['is_active' => false]);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_a_member_can_log_out(): void
    {
        $this->actingAs($this->member())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
