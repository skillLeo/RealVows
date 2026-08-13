<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileBuilderController;
use App\Models\Guardian;
use App\Models\GuardianInvitation;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\Purchase;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ⚠️ EVERY ROUTE IS OPENED, AS SOMEBODY ENTITLED TO OPEN IT.
 *
 * ── Why this file exists ──────────────────────────────────────────────────
 *
 * The trusted contact's dashboard threw RelationNotFoundException on first
 * render and had done so for its entire existence. The screen where every
 * introduction is approved or declined had never once drawn, and the suite was
 * green throughout — because GuardianPhotoRuleTest exercised the presenter that
 * feeds it, correctly and thoroughly, and a unit test on the thing a page uses
 * says nothing whatsoever about whether the page loads.
 *
 * That is the NINTH time this project has shipped something that reported
 * success while checking nothing, and a route table is exactly where this hides:
 * a controller is only ever proved by somebody opening it, and "somebody opens
 * it" is precisely what a unit test replaces.
 *
 * ⚠️ WRITING THIS FILE IMMEDIATELY FOUND THE TENTH. Opening the photograph
 * routes for real surfaced that `Storage::fake('private')` fakes a disk that
 * does not exist — the application uses `local` — so the photograph rule's own
 * positive control had been passing on a stray file left in real storage by an
 * earlier run. See StorageFakesAConfiguredDiskTest. A walk that actually opens
 * things finds what a walk over the things around them cannot.
 *
 * ── ⚠️ THE BAR, AND WHY IT IS NOT MERELY "NO 500" ─────────────────────────
 *
 * "No route returns 5xx" is satisfied by an application that answers 404 to
 * everything. So this asserts BOTH directions:
 *
 *   · no route may return 5xx, and
 *   · no route may return 404 either, unless it is on EXPECTED below with the
 *     exact status it is allowed to answer and a written reason
 *
 * The second half is what makes the first half mean anything. A world fixture
 * that failed to build a Verification would send every verification route to
 * 404, and a 5xx-only test would call that a pass.
 *
 * ── ⚠️ WHAT A NON-GET ROUTE PROVES HERE, AND WHAT IT DOES NOT ─────────────
 *
 * Writes are sent with no payload. That exercises the middleware stack, the
 * route model binding and the FormRequest, and it stops at the validator — so
 * a 302 or 422 from a write proves the stack in front of the handler boots and
 * proves NOTHING about the handler body. It is recorded as a limit rather than
 * dressed up as coverage. The guardian bug lived in a handler body and a write
 * smoke would not have caught it; the GET half is what does.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes whose correct answer to this walk is not a success.
     *
     * ⚠️ EACH ENTRY NAMES THE EXACT STATUS, NOT MERELY THE ROUTE. A blanket
     * exemption would also swallow a genuine 500 from the same route, which
     * turns the list from a record of intent into a blind spot. If one of these
     * starts answering something else, this test still says so.
     *
     * ⚠️ AND EACH CARRIES A REASON. Without one the list is just a way to
     * silence the test, and the next unrenderable page lands on it quietly.
     *
     * @var array<string, array{status: int, reason: string}>
     */
    private const EXPECTED = [
        'demo.login' => [
            'status' => 404,
            'reason' => 'Self-closing by design: the moment one account exists whose address '
                .'is not @realvows.test the buttons and the route stop answering, and this '
                .'world builds ordinary addresses. DemoLoginClosesItselfTest owns that.',
        ],

        'whatsapp.webhook.verify' => [
            'status' => 503,
            'reason' => 'A deliberate, documented 503 meaning "no verify token configured", '
                .'chosen over 403 so the client is not sent to check Meta\'s dashboard when '
                .'the empty field is in his own settings. Pinned to 503 here precisely so a '
                .'real 500 from this handler would still be reported. WhatsAppWebhookTest '
                .'owns the configured path.',
        ],
    ];

    /** @var array<string, mixed> */
    private array $world = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
        Storage::fake('public');

        $this->buildWorld();
    }

    // ─────────────────────────────────────────────────────────────────────
    // The tests
    // ─────────────────────────────────────────────────────────────────────

    public function test_every_readable_route_opens_for_somebody_entitled_to_open_it(): void
    {
        $results = $this->walk(readsOnly: true);

        $this->assertNotEmpty($results, 'No routes were walked at all.');

        /*
         * ⚠️ THE ENUMERATION IS ASSERTED, NOT ASSUMED. Every finding below is
         * about absence of failure, and a walk over zero routes produces the
         * cleanest possible result. The floor is deliberately far below the
         * real count so it does not have to be edited on every new route; it
         * exists to catch a router that returned nothing.
         */
        $this->assertGreaterThan(
            40,
            count($results),
            'The router yielded implausibly few readable routes, so a clean result here '
                .'means the walk found nothing rather than that everything works.'
        );

        $broken = array_filter($results, fn (array $r) => $this->isUnexpected($r) && $r['status'] >= 500);

        $this->assertSame([], $this->format($broken), implode("\n", [
            'These routes threw for a user entitled to open them. A page that cannot render',
            'is not covered by a unit test on the service it calls.',
        ]));

        /*
         * ⚠️ THE HALF THAT MAKES THE OTHER HALF MEAN ANYTHING. An application
         * that answered 404 to every request would satisfy the 5xx check above
         * completely.
         */
        $missing = array_filter($results, fn (array $r) => $this->isUnexpected($r) && $r['status'] === 404);

        $this->assertSame([], $this->format($missing), implode("\n", [
            'These routes answered 404 to an actor who should have reached them. Either the',
            'route is genuinely meant to refuse — in which case add it to EXPECTED with the',
            'status and a reason — or the page is broken in a way a 5xx check cannot see.',
        ]));
    }

    /**
     * ⚠️ AN EXPECTED ENTRY EXCUSES ONE STATUS, NOT A ROUTE. A route on the list
     * that answers anything other than the status recorded for it is reported
     * exactly like an unlisted one.
     *
     * @param  array{name: string, status: int}  $result
     */
    private function isUnexpected(array $result): bool
    {
        $expected = self::EXPECTED[$result['name']]['status'] ?? null;

        return $expected !== $result['status'];
    }

    public function test_every_write_route_boots_its_stack(): void
    {
        /*
         * ⚠️ READ THE LIMIT IN THE CLASS DOCBLOCK BEFORE TRUSTING THIS. A pass
         * here means middleware, model binding and validation survived an empty
         * body. It says nothing about the handler.
         */
        $results = $this->walk(readsOnly: false);

        $this->assertGreaterThan(20, count($results), 'The router yielded implausibly few write routes.');

        $broken = array_filter($results, fn (array $r) => $this->isUnexpected($r) && $r['status'] >= 500);

        $this->assertSame([], $this->format($broken), implode("\n", [
            'These write routes threw before reaching their handler, which means the fault is',
            'in middleware, route model binding or the FormRequest rather than in the body.',
        ]));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), AND THIS FILE IS USELESS WITHOUT IT.
     *
     * Every assertion above is an empty-array check. A walk that resolved no
     * routes, or a request helper that swallowed exceptions and reported 200,
     * would satisfy all of them and report a clean sweep over nothing — which
     * is precisely the shape of failure this file was written to end.
     *
     * So a route that throws the very exception the guardian dashboard threw is
     * registered here and walked by the same code path, and it has to be seen.
     */
    public function test_the_walk_can_still_see_a_broken_route(): void
    {
        Route::middleware('web')->get('/smoke-control-broken', function () {
            // The exact fault that hid on the guardian dashboard for its whole life.
            return MatchRecord::query()->with('matchedProfile')->get();
        })->name('__smoke.control.broken');

        Route::middleware('web')->get('/smoke-control-fine', fn () => response('ok'))
            ->name('__smoke.control.fine');

        $results = collect($this->walk(readsOnly: true))->keyBy('name');

        $this->assertGreaterThanOrEqual(
            500,
            $results['__smoke.control.broken']['status'] ?? 0,
            'A route that throws RelationNotFoundException was walked and not reported as '
                .'broken. Every empty-array assertion in this file is therefore worthless.'
        );

        /*
         * ⚠️ AND THE OTHER DIRECTION. A walk that reported everything as broken
         * would pass the assertion above while being just as dead.
         */
        $this->assertSame(
            200,
            $results['__smoke.control.fine']['status'] ?? 0,
            'A route that plainly works was reported as failing, so the walk marks '
                .'everything and distinguishes nothing.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // The walk
    // ─────────────────────────────────────────────────────────────────────

    /** @return list<array{name: string, uri: string, method: string, status: int}> */
    private function walk(bool $readsOnly): array
    {
        $results = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $method = $this->verbFor($route);

            if ($method === null || ($method === 'GET') !== $readsOnly) {
                continue;
            }

            if ($this->isFrameworkPlumbing($route)) {
                continue;
            }

            $uri = $this->fill($route);

            if ($uri === null) {
                $this->fail(
                    'No fixture for a parameter on '.($route->getName() ?? $route->uri()).' ('
                        .implode(', ', $route->parameterNames()).'). Add one to buildWorld() — '
                        .'silently skipping it is how a page goes unopened for a year.'
                );
            }

            $actor = $this->actorFor($route);

            if (in_array('signed', $route->gatherMiddleware(), true)) {
                $uri = $this->sign($route, $uri);
            }

            $status = $this->request($method, $uri, $actor);

            $results[] = [
                'name' => $route->getName() ?? $route->uri(),
                'uri' => $uri,
                'method' => $method,
                'status' => $status,
            ];
        }

        return $results;
    }

    private function request(string $method, string $uri, ?User $actor): int
    {
        if ($actor) {
            $test = $this->actingAs($actor);
        } else {
            // A guest route walked while signed in proves nothing.
            $this->app['auth']->forgetGuards();
            $test = $this;
        }

        /*
         * ⚠️ EXCEPTION HANDLING IS LEFT ON, AND THAT IS THE POINT. With
         * withoutExceptionHandling() a thrown error becomes a test error rather
         * than a status code, and the walk would stop at the first fault
         * instead of reporting all of them. A 500 in the response IS the
         * finding here.
         */
        $response = match ($method) {
            'GET' => $test->get($uri),
            'POST' => $test->post($uri, []),
            'PUT' => $test->put($uri, []),
            'PATCH' => $test->patch($uri, []),
            'DELETE' => $test->delete($uri),
            default => null,
        };

        return $response?->getStatusCode() ?? 0;
    }

    private function verbFor(RoutingRoute $route): ?string
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            if (in_array($verb, $route->methods(), true)) {
                return $verb;
            }
        }

        return null;
    }

    private function isFrameworkPlumbing(RoutingRoute $route): bool
    {
        $uri = $route->uri();

        // Ignition, Vite, the storage symlink shim. Not ours to answer for.
        return str_starts_with($uri, '_')
            || str_starts_with($uri, 'storage/')
            || $uri === 'up';
    }

    /** The most privileged actor entitled to this route, or null for a guest. */
    private function actorFor(RoutingRoute $route): ?User
    {
        $middleware = $route->gatherMiddleware();

        $has = fn (string $needle) => (bool) array_filter(
            $middleware,
            fn ($m) => is_string($m) && str_starts_with($m, $needle),
        );

        if ($has('admin')) {
            return $this->world['admin'];
        }

        if ($has('guardian')) {
            return $this->world['guardian'];
        }

        /*
         * ⚠️ A GUEST ROUTE MUST BE WALKED AS A GUEST. Signing in first would
         * send every one of them to a redirect, which is non-5xx, non-404 and
         * completely uninformative.
         */
        if ($has('guest')) {
            return null;
        }

        if ($has('auth')) {
            return $this->world['member'];
        }

        return null;
    }

    /** Substitute every parameter, or null if one has no fixture. */
    private function fill(RoutingRoute $route): ?string
    {
        $uri = $route->uri();

        foreach ($route->parameterNames() as $name) {
            $value = $this->world['params'][$name] ?? null;

            if ($value === null) {
                // An optional parameter may simply be dropped.
                if (str_contains($uri, '{'.$name.'?}')) {
                    $uri = str_replace('/{'.$name.'?}', '', $uri);

                    continue;
                }

                return null;
            }

            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], (string) $value, $uri);
        }

        return '/'.ltrim(str_replace('//', '/', $uri), '/');
    }

    private function sign(RoutingRoute $route, string $uri): string
    {
        /*
         * ⚠️ SIGNED BY HAND RATHER THAN THROUGH temporarySignedRoute(). Several
         * of these routes take a plain string parameter rather than a bound
         * model, and reconstructing the named-route argument list per route is
         * more moving parts than signing the URL that was already built.
         */
        $full = URL::to($uri);
        $expires = now()->addHour()->getTimestamp();
        $withExpiry = $full.'?expires='.$expires;

        $signature = hash_hmac('sha256', $withExpiry, config('app.key'));

        return $uri.'?expires='.$expires.'&signature='.$signature;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function format(array $rows): array
    {
        return array_values(array_map(
            fn (array $r) => "{$r['status']}  {$r['method']} {$r['uri']}  ({$r['name']})",
            $rows,
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // The world
    // ─────────────────────────────────────────────────────────────────────

    private function buildWorld(): void
    {
        $admin = User::factory()->create(['email' => 'smoke-admin@realvows.example']);
        $admin->assignRole('super_admin');

        $member = User::factory()->create([
            'email' => 'smoke-member@realvows.example',
            'email_verified_at' => now(),
        ]);
        $member->assignRole('member');

        $hers = Profile::factory()->woman()->create([
            'user_id' => $member->id,
            'submitted_at' => now(),
        ]);
        ProfilePreference::factory()->create(['profile_id' => $hers->id]);

        $verification = Verification::create([
            'profile_id' => $hers->id,
            'status' => Verification::STATUS_PENDING,
            'selfie_path' => 'verifications/'.$hers->id.'/selfie.jpg',
            'code' => 'AB12CD',
        ]);
        Storage::disk(PhotoStorageService::DISK)->put($verification->selfie_path, 'bytes');

        $photo = Photo::create([
            'profile_id' => $hers->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$hers->id.'/p.jpg',
            'webp_path' => null,
            'thumbnail_path' => 'profiles/'.$hers->id.'/p-thumb.jpg',
            'original_filename' => 'p.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);
        Storage::disk(PhotoStorageService::DISK)->put($photo->path, 'bytes');

        $his = Profile::factory()->man()->create(['submitted_at' => now()]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $hers->id,
            'matched_profile_id' => $his->id,
            'total_score' => 84,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        $contact = User::factory()->create(['email' => 'smoke-contact@realvows.example']);

        $guardian = Guardian::create([
            'member_id' => $member->id,
            'user_id' => $contact->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
            /*
             * ⚠️ THE JOINT PHOTOGRAPH IS PART OF THE FIXTURE, NOT AN EXEMPTION.
             *
             * Without it the admin route that reviews it answers 404 honestly,
             * and putting the route on EXPECTED would have recorded "this page
             * refuses" when the truth was "this test never built the thing the
             * page is for". An exemption written around a fixture gap is how a
             * screen goes unopened for a year.
             */
            'joint_photo_path' => 'guardians/joint.jpg',
            'joint_photo_uploaded_at' => now(),
        ]);

        Storage::disk(PhotoStorageService::DISK)->put('guardians/joint.jpg', 'bytes');

        /*
         * ⚠️ ISSUED THROUGH THE REAL API. The token is stored as a hash and the
         * plaintext is returned exactly once, so a hand-built row would produce
         * a link that findByToken() can never match — and the accept route
         * would 404 for a reason that has nothing to do with the page.
         */
        ['token' => $invitationToken] = GuardianInvitation::issue($guardian);

        $purchase = Purchase::create([
            'user_id' => $member->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => Purchase::STATUS_COMPLETED,
            'order_identifier' => (string) Str::uuid(),
            'completed_at' => now(),
        ]);

        $report = Report::create([
            'reporter_profile_id' => $hers->id,
            'subject_profile_id' => $his->id,
            'match_id' => $match->id,
            'reason' => 'other',
            'status' => Report::STATUS_OPEN,
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $member->id,
            'type' => 'other',
            'body' => 'Smoke fixture.',
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $this->world = [
            'admin' => $admin,
            'member' => $member->fresh(),
            'guardian' => $contact->fresh(),
            'params' => [
                'user' => $member->id,
                'match' => $match->id,
                'guardian' => $guardian->id,
                'verification' => $verification->id,
                'photo' => $photo->id,
                'purchase' => $purchase->id,
                'report' => $report->id,
                'ticket' => $ticket->id,
                'order' => $purchase->order_identifier,
                'package' => 'single',
                'step' => array_key_first(ProfileBuilderController::STEP_KEYS),
                'group' => 'general',
                'key' => 'verification-approved',
                'channel' => 'email',
                'token' => $invitationToken,
                'account' => 'boy1',
                'id' => $member->id,
                'hash' => sha1($member->email),
            ],
        ];
    }
}
