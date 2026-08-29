<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MemberHub;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ⚠️ A PAGE NOBODY CAN CLICK TO IS A PAGE THAT DOES NOT EXIST.
 *
 * ── The direction this asks, and why nothing else asked it ────────────────
 *
 * NavigationTest walks every link the server renders and opens it. Correct,
 * and it answers "is every door we drew a real door".
 *
 * NavigationCrawlTest reads the Vue source of each rendered page and requests
 * every target it finds. Also correct, and it answers the same question from
 * the other side of the template.
 *
 * ⚠️ NEITHER OF THEM CAN SEE A DOOR THAT WAS NEVER DRAWN. Both start from the
 * links and work towards the routes. Seven admin screens were registered,
 * permissioned, tested and shipped with no navigation entry at all, and
 * `/introductions` had no member link — the single screen the whole product
 * delivers its work through. Every navigation test passed the entire time,
 * because a missing link is invisible to a test that starts at the links.
 *
 * This starts at the ROUTE TABLE and walks towards the links, which is the only
 * direction that can see an absence.
 *
 * ── ⚠️ WHY THE EXCEPTIONS ARE BY KIND AND NOT BY NAME ─────────────────────
 *
 * Most routes are correctly unlinked: a detail page is reached from the list
 * above it, a signed link arrives by email, a write has no href. Listing them
 * one by one would produce a hundred-line allowlist that everybody appends to
 * and nobody reads, which is a way of turning the test off slowly.
 *
 * So exceptions are declared as KINDS, each with a reason. Adding a route to an
 * existing kind is a judgement somebody can check in one line; inventing a new
 * kind is a thing a reviewer will notice.
 */
class NavigationCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ EACH KIND CARRIES A REASON, AND THE REASON IS THE POINT.
     *
     * @var array<string, array{reason: string, routes: list<string>}>
     */
    private const UNLINKED_BY_DESIGN = [
        'detail-pages' => [
            'reason' => 'Reached from the list above them, which IS linked. A navigation entry '
                .'pointing at one arbitrary record would be meaningless.',
            'routes' => [
                'matches.show', 'admin.matches.show', 'admin.verifications.show',
                'admin.emails.show', 'admin.messages', 'admin.credits',
                'admin.trusted-contacts.photo', 'admin.verifications.photo',
                'admin.verifications.selfie', 'verification.selfie.show',
                'profile.step', 'checkout.show', 'checkout.processing',
                'checkout.result', 'checkout.return', 'guardian.photo',
            ],
        ],

        'arrives-by-link' => [
            'reason' => 'Sent to one person in an email or a message and opened from there. A '
                .'navigation entry could not construct the token or the signature.',
            'routes' => [
                'photos.show', 'receipt', 'unsubscribe', 'trusted-contact.accept',
                'verification.verify', 'password.reset', 'checkout.callback',
            ],
        ],

        'auth-flow' => [
            'reason' => 'The signed-out flow links these to each other. They are unreachable '
                .'once signed in, which is when the navigation exists at all.',
            'routes' => [
                'login', 'register', 'password.request', 'verification.notice',
            ],
        ],

        'not-a-page' => [
            'reason' => 'Machine endpoints and browser plumbing. Nothing renders and nobody '
                .'navigates to them.',
            'routes' => [
                'robots', 'offline', 'whatsapp.webhook.verify',
            ],
        ],

        'admin-landing' => [
            'reason' => '/admin and /admin/dashboard are the same screen. The sidebar links the '
                .'first; the second exists because it is what anybody types.',
            'routes' => [
                'admin.',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_every_readable_route_is_linked_from_somewhere_a_person_can_see(): void
    {
        $linked = $this->everyRenderedHref();

        /*
         * ⚠️ THE LINK SET IS PROVED NON-EMPTY FIRST. If the navigation sources
         * returned nothing, every route below would be reported as unlinked and
         * the failure would look like a hundred broken pages rather than one
         * broken fixture. Worse, if the comparison were inverted, an empty link
         * set would report a clean sweep.
         */
        $this->assertGreaterThan(20, count($linked), 'Almost no links were gathered, so this test is comparing routes against nothing.');

        $exempt = $this->exemptRouteNames();
        $orphans = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();

            if ($name === null
                || str_starts_with($route->uri(), '_')
                || str_starts_with($route->uri(), 'storage/')
                || $route->uri() === 'up') {
                continue;
            }

            $checked++;

            if (in_array($name, $exempt, true)) {
                continue;
            }

            if (! $this->isLinked($route, $linked)) {
                $orphans[] = $name.'  ('.$route->uri().')';
            }
        }

        $this->assertGreaterThan(40, $checked, 'Implausibly few routes were examined.');

        $this->assertSame([], $orphans, implode("\n", [
            'These pages are registered and reachable and nothing links to them, so the only',
            'way to open one is to type the address. Seven admin screens and /introductions',
            'shipped in exactly this state.',
            '',
            'Either add a navigation entry, or add the route to a kind in UNLINKED_BY_DESIGN',
            'whose stated reason genuinely covers it.',
        ]));
    }

    public function test_every_exempted_route_still_exists(): void
    {
        /*
         * ⚠️ THE ALLOWLIST IS ITSELF CHECKED. A renamed or deleted route leaves
         * a dead entry behind, and a dead entry is how the NEXT route with that
         * name inherits an exemption nobody granted it.
         */
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== null) {
                $registered[] = $route->getName();
            }
        }

        $this->assertNotEmpty($registered);

        $stale = array_values(array_diff($this->exemptRouteNames(), $registered));

        $this->assertSame([], $stale, implode("\n", [
            'These names are exempted from the navigation requirement and no longer name any',
            'route. Remove them, or the next route to take one of these names is exempt by',
            'accident.',
        ]));
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above is an empty-array check, and
     * an empty array is what a test that examined nothing produces.
     *
     * A route is registered here that no navigation source mentions, and the
     * comparison has to report it. Then a route that IS linked is checked not
     * to be reported, because a comparison that flags everything is just as
     * dead as one that flags nothing.
     */
    public function test_the_comparison_can_still_see_an_unlinked_page(): void
    {
        Route::middleware('web')->get('/a-page-nothing-links-to', fn () => 'x')
            ->name('orphan.control');

        $linked = $this->everyRenderedHref();
        $exempt = $this->exemptRouteNames();

        $orphans = [];
        $linkedOnes = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true) || $route->getName() === null) {
                continue;
            }

            if (in_array($route->getName(), $exempt, true)) {
                continue;
            }

            if ($this->isLinked($route, $linked)) {
                $linkedOnes[] = $route->getName();
            } else {
                $orphans[] = $route->getName();
            }
        }

        $this->assertContains('orphan.control', $orphans, 'An unlinked route was not reported, so the clean result above means nothing.');

        // And the other direction: a page the sidebar plainly links must not
        // appear in the same list.
        $this->assertContains('admin.verifications', $linkedOnes);
        $this->assertNotContains('admin.verifications', $orphans);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function exemptRouteNames(): array
    {
        $names = [];

        foreach (self::UNLINKED_BY_DESIGN as $kind => $entry) {
            $this->assertNotEmpty($entry['reason'], "The `{$kind}` exemption has no reason.");

            foreach ($entry['routes'] as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Every href a person could see, gathered from the three places the product
     * builds navigation plus the always-mounted chrome.
     *
     * @return list<string>
     */
    private function everyRenderedHref(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');

        $sources = [
            json_encode(Navigation::admin($admin->fresh()), JSON_UNESCAPED_SLASHES),
            json_encode(Navigation::for($admin->fresh()), JSON_UNESCAPED_SLASHES),
            json_encode(Navigation::for($member->fresh()), JSON_UNESCAPED_SLASHES),
            json_encode(MemberHub::for($member->fresh()), JSON_UNESCAPED_SLASHES),

        ];

        /*
         * ⚠️ THE TEMPLATES COUNT TOO, AND BOTH KINDS OF THEM.
         *
         * Reading only the server-built arrays reports the account menu and the
         * legal pages as unlinked, which is false and would push somebody to
         * exempt real orphans alongside them.
         *
         * ⚠️ AND BLADE, NOT ONLY VUE. The public site deliberately ships no Vue
         * and no Inertia — its navigation and footer are Blade partials. A
         * gatherer that read resources/js alone reported /about, /how-it-works
         * and /refunds as pages nothing links to, when they sit in the public
         * nav on every marketing page. That mistake is the exact shape this
         * file exists to catch, made by this file.
         */
        /*
         * ⚠️ app/Support IS SCANNED TOO, AND IT HAS TO BE — R10-C §6.
         *
         * The header and the footer used to hold their own copies of the
         * platform link list and they disagreed with each other on production.
         * They now share PublicState::platformLinks(), which lives in app/Support.
         *
         * The moment that literal moved out of resources/, this guard reported
         * /about as a page nothing links to — the same false-orphan failure the
         * note above describes, produced a second time by the same cause. The
         * extractor reads route('name') out of SOURCE, so the fix is to look
         * where the navigation is now written rather than to hand it a resolved
         * URL, which starts http:// and matches nothing.
         */
        foreach ([resource_path('js'), resource_path('views'), app_path('Support')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (in_array($file->getExtension(), ['vue', 'php'], true)) {
                    $sources[] = File::get($file->getPathname());
                }
            }
        }

        $hrefs = [];

        foreach ($sources as $source) {
            // Quotes and backticks: Login.vue builds its hrefs as template
            // literals, and a regex that ignored backticks would call every one
            // of those an orphan.
            preg_match_all('#["\'`](/[a-z0-9\-/_{}\.]*)["\'`]#i', (string) $source, $matches);

            foreach ($matches[1] as $href) {
                $hrefs[] = $href;
            }

            /*
             * ⚠️ AND BY ROUTE NAME, WHICH IS HOW THE PUBLIC SITE LINKS.
             *
             * The Blade partials write route('how-it-works'), never '/how-it-works'.
             * A gatherer that only understood paths reported /about, /how-it-works
             * and /refunds as pages nothing links to while they sat in the header
             * and footer of every marketing page in the product.
             *
             * Prefixed with @ so a route named `home` can never be confused with
             * a path, and so this half of the set is legible in a failure dump.
             */
            preg_match_all('#\broute\(\s*["\']([a-z0-9\-\._]+)["\']#i', (string) $source, $named);

            foreach ($named[1] as $name) {
                $hrefs[] = '@'.$name;
            }
        }

        return array_values(array_unique($hrefs));
    }

    /** @param list<string> $linked */
    private function isLinked(RoutingRoute $route, array $linked): bool
    {
        if (in_array('@'.$route->getName(), $linked, true)) {
            return true;
        }

        $uri = '/'.ltrim($route->uri(), '/');

        if (in_array($uri, $linked, true)) {
            return true;
        }

        /*
         * A parameterised route is linked if anything points at its static
         * prefix — `/admin/settings/general` links `admin/settings/{group?}`.
         * An optional trailing parameter also means the bare path is the route.
         */
        $prefix = rtrim(preg_replace('#\{[^}]+\}.*$#', '', $uri) ?? $uri, '/');

        if ($prefix !== '' && $prefix !== '/') {
            foreach ($linked as $href) {
                if ($href === $prefix || str_starts_with($href, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }
}
