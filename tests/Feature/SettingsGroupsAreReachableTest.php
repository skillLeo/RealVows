<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\Setting;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ EVERY SETTINGS GROUP IN THE DATABASE MUST BE REACHABLE, AND EVERY GROUP
 * THE ADMIN OFFERS MUST EXIST. BOTH DIRECTIONS, ALWAYS.
 *
 * ── What happened ─────────────────────────────────────────────────────────
 *
 * `guardian` and `ai` had rows in the settings table, a fully built admin form
 * waiting to render them, and no entry in the controller's group list. They
 * appeared on no screen. They could be changed only by opening a database
 * client.
 *
 * ⚠️ FOR `guardian` THAT WAS THE ENTIRE FEATURE. The requirement level is the
 * single switch the whole trusted-contact system hangs off. With no way to
 * reach it, the switch stayed at its default, no member could nominate anybody,
 * every gate was inert — and the feature had been designed, built, tested,
 * reviewed and deployed. The client reported it as "the flow is totally
 * missed", which was exactly right, and no test disagreed with him. Every
 * settings test passed, because every settings test asked about the groups the
 * controller already knew about.
 *
 * ── ⚠️ WHY ONE DIRECTION IS NOT ENOUGH ────────────────────────────────────
 *
 * Asking only "does every rendered group exist" is satisfied by a controller
 * that offers nothing at all — which is precisely the bug above. Asking only
 * "is every stored group rendered" is satisfied by a controller that offers a
 * group with no rows, which renders an empty form that reads as broken.
 *
 * A reader matching nothing and one matching everything are equally dead, so
 * both questions are asked, and both are asked against the LIVE data rather
 * than a list written here — a third copy of the group names would drift from
 * the other two and this file would become the thing it is guarding against.
 */
class SettingsGroupsAreReachableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_every_stored_group_is_offered_by_the_admin(): void
    {
        $stored = Setting::query()->distinct()->pluck('group')->sort()->values()->all();
        $offered = array_keys(SettingsController::groups());

        // The sweep is proved to have found data before anything is concluded.
        $this->assertNotEmpty($stored, 'The settings table is empty, so this test compares nothing.');

        $unreachable = array_values(array_diff($stored, $offered));

        $this->assertSame([], $unreachable, implode("\n", [
            'These settings groups exist in the database and appear on no admin screen.',
            'They can be changed only with a database client, which for `guardian` meant the',
            'switch the entire trusted-contact system hangs off was invisible for weeks.',
        ]));
    }

    public function test_every_offered_group_exists_in_the_database(): void
    {
        $stored = Setting::query()->distinct()->pluck('group')->all();
        $offered = array_keys(SettingsController::groups());

        $this->assertNotEmpty($offered, 'The admin offers no settings groups at all.');

        $empty = array_values(array_diff($offered, $stored));

        $this->assertSame([], $empty, implode("\n", [
            'The admin offers these groups and the database has no rows for them, so each one',
            'renders an empty form. An empty form reads as a broken page, not as a group with',
            'nothing in it.',
        ]));
    }

    public function test_every_offered_group_actually_opens(): void
    {
        /*
         * ⚠️ THE LIST AGREEING WITH ITSELF IS NOT THE SAME AS THE PAGE LOADING.
         * Both assertions above compare two arrays and neither one opens
         * anything. A group present in both lists whose form throws would
         * satisfy them completely — which is the shape of the guardian
         * dashboard bug, transplanted into settings.
         */
        $admin = $this->superAdmin();
        $opened = 0;

        foreach (array_keys(SettingsController::groups()) as $group) {
            $this->actingAs($admin)
                ->get("/admin/settings/{$group}")
                ->assertOk();

            $opened++;
        }

        $this->assertSame(count(SettingsController::groups()), $opened);
        $this->assertGreaterThan(5, $opened, 'Implausibly few groups were opened.');
    }

    public function test_the_sidebar_links_to_every_group(): void
    {
        /*
         * Navigation::admin() renders one link per group. If it built its own
         * list rather than reading the controller's, the two would drift and
         * the drift would surface as a sidebar item that 404s.
         */
        $links = json_encode(Navigation::admin($this->superAdmin()), JSON_UNESCAPED_SLASHES);

        $this->assertNotEmpty($links);

        foreach (array_keys(SettingsController::groups()) as $group) {
            $this->assertStringContainsString(
                "/admin/settings/{$group}",
                (string) $links,
                "The admin sidebar has no link to the `{$group}` settings group.",
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above is an empty-diff check, and a
     * diff between two lists that are both empty, or both read from the same
     * source, is empty for reasons that have nothing to do with correctness.
     *
     * A group is written into the database and nowhere else. It has to be seen.
     */
    public function test_the_comparison_can_still_see_an_unreachable_group(): void
    {
        Setting::create([
            'group' => 'a_group_no_screen_offers',
            'key' => 'example',
            'value' => 'x',
            'type' => 'text',
            'label' => 'Example',
        ]);

        $stored = Setting::query()->distinct()->pluck('group')->all();
        $offered = array_keys(SettingsController::groups());

        $this->assertSame(
            ['a_group_no_screen_offers'],
            array_values(array_diff($stored, $offered)),
            'A group was added to the database with no admin entry and the comparison did '
                .'not report it, so the clean result in the first test means nothing.'
        );

        /*
         * ⚠️ AND THE OTHER DIRECTION. A comparison that flagged everything
         * would pass the assertion above while being just as useless.
         */
        $this->assertNotContains(
            'general',
            array_diff($stored, $offered),
            'A group that is plainly reachable was reported as unreachable.'
        );
    }
}
