<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-click bypass must stop existing when a real person registers.
 *
 * ⚠️ BOTH DIRECTIONS, BECAUSE EITHER ONE ALONE IS A DEAD TEST.
 *
 * A check that always says "closed" passes every security assertion here and
 * silently removes a feature the client asked for and uses. A check that always
 * says "open" passes the demo assertions and leaves members' identity documents
 * one click from a stranger. This project has shipped a guard that matched
 * nothing and a guard that matched everything, so neither is hypothetical.
 */
class DemoLoginClosesItselfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        config()->set('realvows.demo_logins', true);
    }

    private function demoMember(string $email = 'boy1@realvows.test'): User
    {
        return User::factory()->create(['email' => $email]);
    }

    public function test_the_buttons_are_offered_while_only_demo_accounts_exist(): void
    {
        $this->demoMember();
        $this->demoMember('admin@realvows.test');

        $this->assertTrue(DemoLoginController::onlyDemoMembersExist());
        $this->assertNotEmpty(DemoLoginController::buttons());
    }

    public function test_one_real_member_removes_every_button(): void
    {
        $this->demoMember();
        User::factory()->create(['email' => 'umar@example.com']);

        $this->assertFalse(DemoLoginController::onlyDemoMembersExist());
        $this->assertSame([], DemoLoginController::buttons());
    }

    public function test_the_controller_refuses_even_when_the_route_is_registered_anyway(): void
    {
        /*
         * ⚠️ THE ROUTE IS DECLARED HERE ON PURPOSE, AND THAT IS THE TEST.
         *
         * Under APP_ENV=testing the config resolves false, so routes/auth.php
         * never registers this URL and it cannot be reached from a test at all.
         * Asserting a 404 against it would therefore assert nothing: a route
         * that was never registered returns 404 whatever the controller does.
         * ReviewerScope shipped with exactly that shape — every assertion
         * passing against a dead route.
         *
         * So the route is registered unconditionally here, which is precisely
         * the mistake the controller's own re-check exists to survive. What is
         * being tested is the third layer of the defence, on its own, with the
         * first two removed.
         */
        \Illuminate\Support\Facades\Route::get('/demo-login/{account}', DemoLoginController::class)
            ->middleware('web');

        $this->demoMember();

        // Control: it genuinely works while only demo accounts exist. Without
        // this line the 404 below would prove nothing.
        $this->get('/demo-login/boy1')->assertRedirect();
        $this->assertAuthenticated();

        $this->post('/logout');

        User::factory()->create(['email' => 'someone@gmail.com']);

        $this->get('/demo-login/boy1')->assertNotFound();
        $this->assertGuest();
    }

    public function test_the_flag_still_governs_independently(): void
    {
        $this->demoMember();

        config()->set('realvows.demo_logins', false);

        $this->assertSame([], DemoLoginController::buttons());
    }

    public function test_a_demo_address_inside_a_longer_string_does_not_count_as_demo(): void
    {
        /*
         * ⚠️ THE LIKE PATTERN HAS TO ANCHOR AT THE END. A `%@realvows.test%`
         * pattern would treat `attacker@realvows.test.evil.com` as a demo
         * account, and the bypass would stay open with a real address in the
         * table. `not like '%@realvows.test'` anchors, and this proves it.
         */
        $this->demoMember();
        User::factory()->create(['email' => 'someone@realvows.test.example.com']);

        $this->assertFalse(DemoLoginController::onlyDemoMembersExist());
    }
}
