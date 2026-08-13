<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\Admin\SettingsController;
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

    /**
     * ⚠️ THE SAME WALK, FOR THE SIDEBAR, AND IT MATTERS MORE HERE.
     *
     * The top bar carries four items and was checked. The admin sidebar
     * carries sixteen across four sections, including eight configuration
     * groups whose hrefs are built by string concatenation — which is exactly
     * the shape that produces a link to a group that does not exist.
     *
     * The permissions behind these were read off routes/web.php one at a time
     * rather than inferred from section names. `payments` and `emails` sit
     * behind settings.update, not a payments permission; guessing would have
     * produced a sidebar link that 404s for precisely the person who needs it,
     * which is the defect this whole class was written to stop.
     */
    #[DataProvider('staffRoles')]
    public function test_every_admin_sidebar_item_opens(string $role): void
    {
        $user = $this->userWithRole($role);
        $sections = Navigation::admin($user);

        $this->assertNotEmpty($sections, "No admin sidebar was built for {$role}.");

        $walked = 0;

        foreach ($sections as $section) {
            $this->assertNotEmpty(
                $section['items'],
                "The {$role} sidebar renders an empty '{$section['label']}' heading."
            );

            foreach ($section['items'] as $item) {
                $response = $this->actingAs($user)->followingRedirects()->get($item['href']);

                $this->assertSame(
                    200,
                    $response->getStatusCode(),
                    "The {$role} sidebar offers {$item['label']} ({$item['href']}), which answered "
                        ."{$response->getStatusCode()}."
                );

                $walked++;
            }
        }

        /*
         * ⚠️ PROVE THE WALK ACTUALLY WALKED. A sidebar that built no items
         * would satisfy every assertion in the loop above by never entering
         * it, and this file would report the navigation as sound.
         */
        $this->assertGreaterThanOrEqual(5, $walked, "Only {$walked} sidebar items were checked for {$role}.");
    }

    public function test_the_sidebar_carries_every_settings_group(): void
    {
        $sections = collect(Navigation::admin($this->userWithRole('super_admin')));

        $configuration = $sections->firstWhere('key', 'configuration');

        $this->assertNotNull($configuration, 'The owner has no configuration section.');

        /*
         * Compared against the controller that serves the groups, not against
         * a list written out here. A second list would drift, and the drift
         * shows up as a group nobody can reach.
         */
        $this->assertSame(
            array_keys(SettingsController::groups()),
            array_map(
                fn (array $item) => str_replace('settings.', '', $item['key']),
                $configuration['items'],
            ),
        );
    }

    public function test_a_reviewer_sees_only_the_sections_they_may_open(): void
    {
        $sections = collect(Navigation::admin($this->userWithRole('admin')));

        // The control: a reviewer's job is the queues, so they must have them.
        $this->assertNotNull($sections->firstWhere('key', 'review'));

        // And must not be shown the platform's credentials.
        $this->assertNull(
            $sections->firstWhere('key', 'configuration'),
            'A reviewer is being offered the configuration section.'
        );
    }

    public function test_a_member_gets_no_admin_sidebar_at_all(): void
    {
        $sections = collect(Navigation::admin($this->userWithRole('member')));

        /*
         * A member holds no permissions, so every staff section filters away.
         * What remains is their own account — and nothing else may appear.
         */
        $this->assertSame(['you'], $sections->pluck('key')->all());
    }

    public function test_a_reviewer_is_not_offered_settings(): void
    {
        $reviewer = $this->userWithRole('admin');

        // The premise of the test: this role genuinely cannot open settings.
        $this->actingAs($reviewer)->get('/admin/settings')->assertNotFound();

        $keys = array_column(Navigation::for($reviewer), 'key');

        $this->assertNotContains('configuration', $keys);
        $this->assertSame(['queue', 'matches', 'account'], $keys);
    }

    public function test_an_owner_is_offered_configuration(): void
    {
        $keys = array_column(Navigation::for($this->userWithRole('super_admin')), 'key');

        /*
         * ⚠️ `configuration`, not `settings`. The member's own account page is
         * titled Settings, so staff were shown two items meaning entirely
         * different things — one holding a phone number, the other holding the
         * payment gateway credentials — under the same word in the same bar.
         */
        $this->assertSame(['queue', 'matches', 'configuration', 'account'], $keys);
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
     * ⚠️ THE ROOT-CAUSE TEST. Read this before adding a prop.
     *
     * Chrome was only half the problem. The Account page carried its own
     * Administration card with a hardcoded /admin/settings button, gated on
     * an `isAdmin` flag its controller sent — a second role check one layer
     * down, offering a reviewer a link that 404'd.
     *
     * A payload test cannot catch that: the href is compiled into the Vue
     * template, so it never appears in the props at all. What DOES appear,
     * and what the defect cannot exist without, is the role flag itself.
     *
     * So the invariant is enforced at the root: no role-shaped value may
     * reach the browser on any page. Without one there is nothing for a
     * template to gate on, and the dead link cannot be written. Where a
     * component needs to know what someone may do, it reads `nav`, which is
     * built from the permissions the routes actually enforce.
     */
    public function test_no_role_flag_ever_reaches_the_browser(): void
    {
        $roleShaped = '/^(is_?admin|is_?staff|is_?super_?admin|roles?|permissions?)$/i';

        $scan = function (array $node, string $path) use (&$scan, $roleShaped, &$found) {
            foreach ($node as $key => $value) {
                if (is_string($key) && preg_match($roleShaped, $key)) {
                    $found[] = "{$path}.{$key}";
                }

                if (is_array($value)) {
                    $scan($value, "{$path}.{$key}");
                }
            }
        };

        foreach (['member', 'admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);

            foreach (['/account', '/dashboard'] as $url) {
                $found = [];
                $scan($this->actingAs($user)->get($url)->viewData('page')['props'], $url);

                $this->assertSame(
                    [],
                    $found,
                    "A role flag reached the browser for {$role}: ".implode(', ', $found)
                        .'. Gate on `nav` instead — a role flag makes every reader guess '
                        .'which permissions it implies, and eventually one guesses wrong.'
                );
            }
        }
    }

    /**
     * The complementary half: where a destination DOES travel through props,
     * a reviewer must never be sent one they cannot open.
     */
    public function test_no_page_offers_a_reviewer_a_destination_they_cannot_open(): void
    {
        $reviewer = $this->userWithRole('admin');

        foreach (['/account', '/dashboard', '/admin/verifications'] as $url) {
            // JSON_UNESCAPED_SLASHES matters: without it json_encode writes
            // "\/admin\/settings" and a search for the plain path finds
            // nothing, so this assertion would pass even with the bug present.
            $payload = json_encode(
                $this->actingAs($reviewer)->get($url)->viewData('page')['props'],
                JSON_UNESCAPED_SLASHES
            );

            $this->assertStringNotContainsString(
                '/admin/settings',
                $payload,
                "{$url} offers a reviewer /admin/settings, which 404s for them."
            );
        }
    }

    public function test_an_owner_still_gets_the_administration_shortcuts(): void
    {
        $payload = json_encode(
            $this->actingAs($this->userWithRole('super_admin'))->get('/account')->viewData('page')['props'],
            JSON_UNESCAPED_SLASHES
        );

        $this->assertStringContainsString('/admin/settings', $payload);
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
