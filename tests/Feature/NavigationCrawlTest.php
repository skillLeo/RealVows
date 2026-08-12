<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ⚠️ NO RENDERED NAVIGATION TARGET MAY BE UNREACHABLE BY THE PERSON LOOKING
 * AT IT.
 *
 * This is the property that actually matters, and it is deliberately BLIND
 * TO HOW THE LINK WAS GATED. A link gated on a role flag, gated on the wrong
 * permission, or gated on nothing at all are the same defect in three
 * costumes, and NavigationTest only catches the first.
 *
 * ── How it works, and its one honest limit ───────────────────────────────
 *
 * Inertia renders links in the browser, not on the server: the HTML response
 * is a single div carrying a `data-page` JSON blob. There is no server-side
 * DOM to parse, and adding a headless browser to a shared-hosting project
 * for one test is a heavy dependency.
 *
 * So instead of parsing rendered HTML, this resolves which Vue component the
 * server said it was rendering, reads that component's SOURCE plus the chrome
 * that is always mounted around it, extracts every link target, and requests
 * each one as that role.
 *
 * ⚠️ THE LIMIT: a target computed entirely at runtime from data — `:href="l.href"`
 * — cannot be resolved statically. Those all come from the server-built `nav`
 * array, which NavigationTest already walks end to end. Between the two
 * tests, both halves are covered; neither covers both alone.
 */
class NavigationCrawlTest extends TestCase
{
    use RefreshDatabase;

    /** Mounted on every authenticated screen, so their links are always live. */
    private const ALWAYS_RENDERED = [
        'resources/js/Layouts/AppLayout.vue',
        'resources/js/Components/BottomTabBar.vue',
        'resources/js/Components/AccountMenu.vue',
    ];

    /**
     * Targets that are correct to render and correct not to resolve to 200.
     * Each needs a reason, or it is a way to silence the test.
     */
    private const EXPECTED_NON_200 = [
        // Public marketing pages carry these; they are 200 but not worth
        // asserting per role.
        '/login' => 'guest-only route, redirects an authenticated member',
        '/register' => 'guest-only route, redirects an authenticated member',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        /*
         * ⚠️ THE TRUSTED-CONTACT LINK IS POLICY-GATED, SO THIS TEST RUNS IN THE
         * STATE WHERE IT IS RENDERED.
         *
         * The account page renders that link only when the policy offers the
         * feature, and the route 404s when it does not. This crawler reads
         * component SOURCE rather than rendered HTML — a documented limit — so
         * it sees the href regardless of the v-if and reported a broken link.
         *
         * The tempting fix is EXPECTED_NON_200 with a reason. That would be
         * wrong: it excuses the link permanently, including when the feature IS
         * switched on and the link genuinely matters. Turning the policy on
         * here means the link is really followed and really checked.
         *
         * ⚠️ THIS IS THE ONLY PLACE THE REQUIREMENT IS SET IN A TEST FIXTURE
         * FOR CONVENIENCE. It does not change the shipped default, which is
         * unset by design.
         */
        app(\App\Services\Settings\SettingsService::class)->set(
            \App\Support\GuardianPolicy::GROUP,
            \App\Support\GuardianPolicy::KEY,
            \App\Support\GuardianPolicy::OPTIONAL,
        );
    }

    public static function roles(): array
    {
        return ['member' => ['member'], 'reviewer' => ['admin'], 'owner' => ['super_admin']];
    }

    /**
     * Real data, so dynamic targets resolve to something that exists rather
     * than 404ing for the boring reason.
     */
    private function seedFor(User $user): array
    {
        $profile = Profile::factory()->woman()->create(['user_id' => $user->id]);
        Verification::factory()->verified()->create(['profile_id' => $profile->id]);
        ProfilePreference::factory()->create(['profile_id' => $profile->id]);

        $them = Profile::factory()->man()->create();
        Verification::factory()->verified()->create(['profile_id' => $them->id]);

        $unlocked = MatchRecord::create([
            'seeker_profile_id' => $profile->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'package_key' => 'triple',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'credits_granted' => 3,
            'status' => Purchase::STATUS_COMPLETED,
            'gateway' => 'powertranz',
            'gateway_mode' => 'sandbox',
            'order_identifier' => (string) Str::uuid(),
            'completed_at' => now(),
        ]);

        return [
            'match' => $unlocked->id,
            'purchase' => $purchase->id,
            'user' => $user->id,
            'profile' => $profile->id,
        ];
    }

    /**
     * Pages to start from. Everything an authenticated person can open —
     * not only what the nav offers, because a link on an unlisted page is
     * still a link somebody can click.
     */
    private function startingPoints(string $role): array
    {
        $member = ['/dashboard', '/account', '/packages', '/purchases', '/profile'];
        $staff = ['/dashboard', '/account', '/admin/verifications', '/admin/matches'];

        return match ($role) {
            'member' => $member,
            'admin' => $staff,
            'super_admin' => [...$staff, '/admin/settings', '/admin/payments'],
        };
    }

    #[DataProvider('roles')]
    public function test_no_rendered_link_is_unreachable_by_the_role_that_sees_it(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $ids = $this->seedFor($user->fresh());
        $user = $user->fresh();

        $checked = 0;
        $broken = [];

        foreach ($this->startingPoints($role) as $page) {
            $response = $this->actingAs($user)->get($page);

            // The starting point itself may legitimately redirect (a member's
            // /profile resolves to their step). Follow it once to find the
            // component actually rendered.
            if ($response->isRedirect()) {
                $response = $this->actingAs($user)->followingRedirects()->get($page);
            }

            if ($response->getStatusCode() !== 200) {
                continue; // Not reachable by this role at all; nothing rendered.
            }

            $component = $response->viewData('page')['component'] ?? null;

            if (! $component) {
                continue;
            }

            foreach ($this->targetsFor($component, $ids) as $target) {
                if (array_key_exists($target, self::EXPECTED_NON_200)) {
                    continue;
                }

                $status = $this->actingAs($user)->followingRedirects()->get($target)->getStatusCode();
                $checked++;

                if ($status !== 200) {
                    $broken[] = "{$page} (component {$component}) renders {$target} → {$status}";
                }
            }
        }

        $this->assertGreaterThan(
            5,
            $checked,
            "Only {$checked} links were crawled for {$role}. The extractor has probably stopped matching."
        );

        $this->assertSame(
            [],
            $broken,
            "Navigation targets that do not open for a {$role}:\n  ".implode("\n  ", $broken)
        );
    }

    /**
     * Every link target a component can render: its own source, the chrome
     * always mounted around it, and — the part that matters — every component
     * it imports, recursively.
     *
     * ⚠️ Following imports is not thoroughness for its own sake. Without it
     * this test had a real blind spot: MatchCard.vue renders the link to a
     * match, but it is a CHILD of Dashboard rather than a page or chrome, so
     * its link was never crawled. A page's links are not only the ones
     * written in the page.
     *
     * @return list<string>
     */
    private function targetsFor(string $component, array $ids): array
    {
        $roots = self::ALWAYS_RENDERED;
        $roots[] = 'resources/js/Pages/'.str_replace('.', '/', $component).'.vue';

        $seen = [];
        $targets = [];

        foreach ($roots as $relative) {
            $this->walk(base_path($relative), $ids, $seen, $targets);
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param  array<string, true>  $seen
     * @param  list<string>  $targets
     */
    private function walk(string $path, array $ids, array &$seen, array &$targets): void
    {
        $path = realpath($path) ?: $path;

        // Cycles are normal in a component tree, not an error.
        if (isset($seen[$path]) || ! File::exists($path)) {
            return;
        }

        $seen[$path] = true;
        $source = File::get($path);

        $targets = [...$targets, ...$this->extract($source, $ids)];

        // `import Foo from '@/Components/Foo.vue'` — the @ alias is
        // resources/js, per vite.config.js.
        preg_match_all("/from\s+'@\/([^']+\.vue)'/", $source, $m);

        foreach ($m[1] as $relative) {
            $this->walk(base_path('resources/js/'.$relative), $ids, $seen, $targets);
        }

        // Relative imports between siblings: './MatchCard.vue'
        preg_match_all("/from\s+'(\.\/[^']+\.vue)'/", $source, $m);

        foreach ($m[1] as $relative) {
            $this->walk(dirname($path).'/'.ltrim($relative, './'), $ids, $seen, $targets);
        }
    }

    /**
     * @return list<string>
     */
    private function extract(string $source, array $ids): array
    {
        $found = [];

        // Static:  href="/packages"
        preg_match_all('/\bhref="(\/[^"{}$`]*)"/', $source, $m);
        $found = [...$found, ...$m[1]];

        // Dynamic: :href="`/matches/${match.id}`" — resolved against real ids
        // so a 404 means the LINK is wrong, not that the fixture is empty.
        preg_match_all('/:href="`(\/[^`]*)`"/', $source, $m);

        foreach ($m[1] as $template) {
            $resolved = preg_replace_callback('/\$\{[^}]+\}/', function (array $token) use ($ids, $template) {
                return match (true) {
                    str_contains($template, '/matches/') => (string) $ids['match'],
                    str_contains($template, '/checkout/') => 'triple',
                    str_contains($template, '/admin/users/') => (string) $ids['user'],
                    str_contains($template, '/admin/matches/') => (string) $ids['match'],
                    str_contains($template, '/admin/payments/') => (string) $ids['purchase'],
                    str_contains($template, '/admin/settings/') => 'general',
                    default => '1',
                };
            }, $template);

            // Anything still carrying an unresolved token is skipped rather
            // than requested as a literal — a 404 on "/x/${y}" would be noise.
            if (! str_contains($resolved, '${')) {
                $found[] = $resolved;
            }
        }

        return array_values(array_filter(
            $found,
            fn ($t) => $t !== '' && ! str_starts_with($t, '//') && ! str_contains($t, ' ')
        ));
    }

    /**
     * ⚠️ POSITIVE CONTROL for the crawl itself.
     *
     * A crawler that has silently stopped matching finds no broken links and
     * reports success, which is the most dangerous kind of green. This proves
     * the extractor can still see a link in a real component file.
     */
    public function test_the_extractor_actually_finds_links(): void
    {
        $targets = $this->targetsFor('Dashboard', [
            'match' => 1, 'purchase' => 1, 'user' => 1, 'profile' => 1,
        ]);

        $this->assertNotEmpty($targets, 'The extractor found no links at all in Dashboard.vue.');
        $this->assertContains('/packages', $targets);
        $this->assertContains('/purchases', $targets);

        // ⚠️ From MatchCard.vue, a CHILD of Dashboard — proves imports are
        // being followed. Without this the crawl silently skipped every link
        // that lives in a child component, which was most of them.
        $this->assertContains(
            '/matches/1',
            $targets,
            'Imports are no longer being followed, so links in child components are invisible.'
        );
    }

    /** And that it can resolve a dynamic target rather than skipping them all. */
    public function test_the_extractor_resolves_dynamic_targets(): void
    {
        $resolved = $this->extract(
            '<a :href="`/matches/${match.id}`">x</a>',
            ['match' => 42, 'purchase' => 1, 'user' => 1, 'profile' => 1]
        );

        $this->assertSame(['/matches/42'], $resolved);
    }
}
