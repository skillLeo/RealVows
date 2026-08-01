<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The product's navigation must never offer a door that is shut.
 *
 * A reviewer once saw a Settings tab they could not open, because the nav
 * asked "is this person staff?" while the route asked "does this person hold
 * settings.update?". This walks every item the server renders for every role
 * and opens it, so that gap cannot reappear silently.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    public static function roles(): array
    {
        return [
            'member' => ['member'],
            'reviewer' => ['admin'],
            'owner' => ['super_admin'],
        ];
    }

    /**
     * The load-bearing test. Every rendered nav item, for every role, must
     * return 200 for the person it was rendered to.
     */
    #[DataProvider('roles')]
    public function test_every_nav_item_opens_for_the_role_it_is_rendered_to(string $role): void
    {
        $user = $this->userWithRole($role);
        $items = Navigation::for($user);

        $this->assertNotEmpty($items, "No navigation was built for {$role}.");

        foreach ($items as $item) {
            /*
             * Redirects are followed rather than forbidden: /profile sends a
             * member on to whichever step they had reached, which is correct
             * behaviour. What is asserted is that the item LANDS somewhere —
             * a chain ending in a 404 fails here just as a direct 404 does.
             */
            $response = $this->actingAs($user)->followingRedirects()->get($item['href']);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "The {$role} nav offers {$item['label']} ({$item['href']}), which answered "
                    ."{$response->getStatusCode()}. Navigation must never render a link that does not open."
            );
        }
    }

    public function test_a_reviewer_is_not_offered_settings(): void
    {
        $reviewer = $this->userWithRole('admin');

        // The premise of the test: this role genuinely cannot open settings.
        $this->actingAs($reviewer)->get('/admin/settings')->assertNotFound();

        $keys = array_column(Navigation::for($reviewer), 'key');

        $this->assertNotContains('settings', $keys);
        $this->assertSame(['queue', 'matches', 'account'], $keys);
    }

    public function test_an_owner_is_offered_settings(): void
    {
        $keys = array_column(Navigation::for($this->userWithRole('super_admin')), 'key');

        $this->assertSame(['queue', 'matches', 'settings', 'account'], $keys);
    }

    public function test_a_member_is_offered_the_member_tabs_and_no_admin_area(): void
    {
        $keys = array_column(Navigation::for($this->userWithRole('member')), 'key');

        $this->assertSame(['matches', 'profile', 'packages', 'account'], $keys);
    }

    /**
     * Staff have no member profile to build and no packages to buy. Offering
     * them either is the dead end this class replaced.
     */
    #[DataProvider('staffRoles')]
    public function test_staff_are_never_offered_member_only_destinations(string $role): void
    {
        $keys = array_column(Navigation::for($this->userWithRole($role)), 'key');

        $this->assertNotContains('profile', $keys);
        $this->assertNotContains('packages', $keys);
    }

    public static function staffRoles(): array
    {
        return ['reviewer' => ['admin'], 'owner' => ['super_admin']];
    }

    public function test_nothing_is_offered_to_a_signed_out_visitor(): void
    {
        $this->assertSame([], Navigation::for(null));
    }

    /**
     * The role is gone from the shared props on purpose: it was a role check
     * standing in for a permission check, and every reader had to guess which
     * permissions it implied.
     */
    public function test_the_browser_is_never_told_a_persons_role(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get('/dashboard');

        $props = $response->viewData('page')['props'];

        $this->assertArrayNotHasKey('is_admin', $props['auth']['user']);
        $this->assertArrayHasKey('nav', $props);
    }

    /**
     * A member who has not confirmed their email is redirected to do so —
     * which is intended, and is not a broken link. What must never happen is
     * a 404.
     */
    public function test_an_unverified_member_is_redirected_rather_than_dead_ended(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('member');

        foreach (Navigation::for($user->fresh()) as $item) {
            $status = $this->actingAs($user)->get($item['href'])->getStatusCode();

            $this->assertNotSame(404, $status, "{$item['href']} 404'd for an unverified member.");
            $this->assertContains($status, [200, 302], "{$item['href']} answered {$status}.");
        }
    }
}
