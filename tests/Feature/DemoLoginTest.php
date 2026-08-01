<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-click demo sign-in is an authentication bypass, so its containment
 * is worth testing more carefully than the feature itself.
 *
 * The suite runs with APP_ENV=testing, which is not local — so these tests
 * are exercising exactly the state a staging or production server is in.
 */
class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_the_bypass_is_off_by_default_outside_local(): void
    {
        $this->assertFalse((bool) config('realvows.demo_logins'));
    }

    public function test_the_route_does_not_exist_outside_local(): void
    {
        // Absent, not merely defended. There is nothing here to probe.
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_contains($route->uri(), 'demo-login')
            )
        );

        $this->get('/demo-login/admin')->assertNotFound();
    }

    public function test_the_login_page_renders_no_demo_buttons_outside_local(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('demoAccounts', []));
    }

    public function test_the_button_list_is_empty_when_the_flag_is_off(): void
    {
        $this->assertSame([], DemoLoginController::buttons());
    }

    public function test_even_with_the_flag_forced_on_it_refuses_a_real_members_address(): void
    {
        // Belt and braces. Force the bypass on, then confirm it still cannot
        // be pointed at a genuine account — only @realvows.test is reachable.
        config(['realvows.demo_logins' => true]);

        $real = User::factory()->create(['email' => 'someone@gmail.com']);
        $real->assignRole('member');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        app(DemoLoginController::class)(request(), 'someone@gmail.com');
    }
}
