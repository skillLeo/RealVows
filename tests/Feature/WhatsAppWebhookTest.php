<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsService;
use App\Support\WhatsAppWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * ⚠️ THE HANDSHAKE, AND THE PROOF THAT IT IS STILL ONLY A HANDSHAKE.
 *
 * This endpoint exists ahead of Phase 3 for one reason: Meta will not issue a
 * phone number ID until a callback URL answers a verification challenge, and
 * business verification is the longest lead time in the project. Nothing here
 * processes a message, and several of these tests exist specifically to keep
 * it that way — "we will wire it up later" turns into "it half works" without
 * something failing the build.
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/webhook/whatsapp';

    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        // Never the real log file: these tests assert on its contents, and a
        // shared file makes them pass or fail depending on run order.
        $this->log = storage_path('logs/whatsapp-webhook-test.log');
        File::delete($this->log);
        config(['logging.channels.whatsapp-webhook.path' => $this->log]);
        Log::forgetChannel('whatsapp-webhook');
    }

    protected function tearDown(): void
    {
        File::delete($this->log);

        parent::tearDown();
    }

    private function storeToken(string $token = 'a-test-verify-token'): string
    {
        app(SettingsService::class)->set('whatsapp', 'verify_token', $token);

        return $token;
    }

    private function logContents(): string
    {
        return File::exists($this->log) ? File::get($this->log) : '';
    }

    /** A realistic delivery, with a real-shaped number and a message body. */
    private function delivery(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1553517519889000',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '18765550142', 'phone_number_id' => '109876543'],
                        'contacts' => [['profile' => ['name' => 'Aisha Mohammed'], 'wa_id' => '18765550199']],
                        'messages' => [[
                            'from' => '18765550199',
                            'id' => 'wamid.HBgLMTg3NjU1NTAxOTk',
                            'timestamp' => '1754380800',
                            'type' => 'text',
                            'text' => ['body' => 'Please stop sending me these messages'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    // ── The handshake ────────────────────────────────────────────────────────

    public function test_the_handshake_echoes_the_challenge_exactly(): void
    {
        $token = $this->storeToken();

        $response = $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=1158201444&hub.verify_token='.$token);

        $response->assertOk();

        /*
         * ⚠️ BYTE FOR BYTE. Meta compares the whole response body, so a JSON
         * wrapper, a trailing newline or a stray whitespace character all fail
         * verification while looking correct in a browser.
         */
        $this->assertSame('1158201444', $response->getContent());
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5) — AND THE BUG THIS ENDPOINT WOULD HAVE HAD.
     *
     * PHP rewrites every dot in a query-string key to an underscore on the way
     * into $_GET, because dots are illegal in PHP variable names. So Meta's
     * `hub.mode` arrives as `hub_mode`, and `$request->query('hub.mode')` reads
     * the dot as nested-array access and finds nothing either.
     *
     * Both obvious readers therefore return null and the endpoint answers 403
     * to a perfectly correct handshake — a failure that is indistinguishable
     * from a wrong token, and that would be hunted for in Meta's dashboard.
     *
     * This proves the reader survives the mangling AND that it is genuinely
     * reading the dotted form rather than passing because both spellings
     * happen to be present.
     */
    public function test_the_reader_survives_phps_dot_mangling(): void
    {
        $webhook = app(WhatsAppWebhook::class);

        $request = \Illuminate\Http\Request::create(
            self::URL.'?hub.mode=subscribe&hub.challenge=xyz&hub.verify_token=t'
        );

        // The control: confirm the mangling is real on this PHP build, so the
        // test cannot quietly become vacuous on a version that stops doing it.
        $this->assertArrayNotHasKey(
            'hub.mode',
            $request->query->all(),
            'This PHP no longer mangles dotted query keys, so this control proves nothing. '
                .'Re-check that WhatsAppWebhook::hubParameter is still needed.'
        );

        $this->assertSame('subscribe', $webhook->hubParameter($request, WhatsAppWebhook::MODE));
        $this->assertSame('xyz', $webhook->hubParameter($request, WhatsAppWebhook::CHALLENGE));

        // And it does not invent a value when the parameter is genuinely absent.
        $this->assertNull($webhook->hubParameter($request, 'hub.nonsense'));
    }

    public function test_a_wrong_verify_token_is_refused_and_the_challenge_is_not_echoed(): void
    {
        $this->storeToken();

        $response = $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=1158201444&hub.verify_token=wrong');

        $response->assertForbidden();

        $this->assertStringNotContainsString(
            '1158201444',
            $response->getContent(),
            'The challenge is echoed even when the token is wrong, so anybody who can reach this '
                .'URL can complete Meta verification against our number.'
        );
    }

    /**
     * ⚠️ AN EMPTY TOKEN SETTING MUST NEVER MEAN "MATCHES ANYTHING".
     *
     * This is the exact failure class Rule 5 exists for: with no token stored,
     * a naive comparison of null against null succeeds and the endpoint
     * verifies for any caller at all, while reporting success.
     */
    public function test_an_unconfigured_token_refuses_rather_than_matching_anything(): void
    {
        // Deliberately not stored.
        $response = $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=1158201444&hub.verify_token=');

        $this->assertSame(503, $response->status());
        $this->assertStringNotContainsString('1158201444', $response->getContent());

        // And it says which of the two problems it is, in the log.
        $this->assertStringContainsString('no verify token configured', $this->logContents());
    }

    public function test_a_wrong_hub_mode_is_refused(): void
    {
        $token = $this->storeToken();

        $this->get(self::URL.'?hub.mode=unsubscribe&hub.challenge=abc&hub.verify_token='.$token)
            ->assertStatus(400);
    }

    /** ⚠️ The token must never reach the log, even on a failed attempt. */
    public function test_the_verify_token_is_never_logged(): void
    {
        $this->storeToken('super-secret-token-value');

        $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=abc&hub.verify_token=super-secret-token-value');
        $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=abc&hub.verify_token=a-guess');

        $log = $this->logContents();

        $this->assertStringNotContainsString('super-secret-token-value', $log);
        $this->assertStringNotContainsString('a-guess', $log);
        $this->assertStringContainsString('verified, challenge echoed', $log);
    }

    // ── Delivery ─────────────────────────────────────────────────────────────

    public function test_a_delivery_is_acknowledged(): void
    {
        $this->postJson(self::URL, $this->delivery())
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    /**
     * ⚠️ THIS CHECKS THE ROUTE'S MIDDLEWARE, NOT A REQUEST. RULE 5.
     *
     * The obvious test — POST without a CSRF token and assert 200 — proves
     * nothing at all, because VerifyCsrfToken returns early when
     * `runningUnitTests()` is true. It passes whether the middleware is
     * excluded or not.
     *
     * It passed here while the live endpoint answered 419 to every delivery
     * from Meta, because ->middleware() had been chained AFTER ->group() and
     * applied to nothing. A green suite and a broken webhook, which is the
     * exact shape Rule 5 exists to catch.
     *
     * The only honest question is what the router actually gathered.
     */
    public function test_the_route_really_is_outside_csrf_and_the_session(): void
    {
        $route = app('router')->getRoutes()->getByName('whatsapp.webhook.receive');

        $this->assertNotNull($route, 'The delivery route is not registered.');

        $excluded = $route->excludedMiddleware();

        /*
         * ⚠️ THE BASE CLASS, NOT THE SUBCLASS, IS WHAT HAS TO BE NAMED.
         *
         * VerifyCsrfToken still exists but now EXTENDS PreventRequestForgery,
         * and the web group registers the parent. Excluding only the subclass
         * removes nothing — which is precisely the bug that shipped.
         */
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            $excluded,
            'Only VerifyCsrfToken is excluded. It is a SUBCLASS of PreventRequestForgery, which '
                .'is what the web group actually registers, so the route keeps full CSRF '
                .'protection and answers 419 to every delivery from Meta.'
        );

        foreach ([
            \Illuminate\Session\Middleware\StartSession::class => 'session startup, which mints a throwaway session row per delivery',
            \App\Http\Middleware\HandleInertiaRequests::class => 'the Inertia middleware',
        ] as $middleware => $what) {
            $this->assertContains($middleware, $excluded, "The webhook still runs {$what}.");
        }

        /*
         * ⚠️ POSITIVE CONTROL (Rule 5). Two of them, because the first version
         * of this test read gatherRouteMiddleware() — which returns the
         * unexpanded group name 'web' and therefore contains no middleware
         * class at all. Every assertNotContains passed against a two-element
         * array while the live endpoint returned 419.
         *
         * So: prove the list is non-empty, and prove it does NOT contain
         * something that is genuinely still in the stack.
         */
        $this->assertNotEmpty($excluded, 'The exclusion list is empty, so nothing above proves anything.');

        $this->assertNotContains(
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            $excluded,
            'Everything is being excluded, so this reader cannot distinguish present from absent.'
        );

        $this->assertContains('throttle:whatsapp-webhook', $route->middleware());
    }

    /** And the same exclusions reach the GET, which is what Meta verifies. */
    public function test_the_handshake_route_is_also_outside_the_session(): void
    {
        $route = app('router')->getRoutes()->getByName('whatsapp.webhook.verify');

        $this->assertNotNull($route);

        $this->assertContains(
            \Illuminate\Session\Middleware\StartSession::class,
            $route->excludedMiddleware()
        );
    }

    /**
     * ⚠️ THE GATE ON PHASE 3, ASSERTED.
     *
     * The endpoint is allowed to acknowledge and log. The moment it dispatches
     * a job or writes a row, message handling has started — which is Phase 3
     * work, gated on the client's written acceptance of Phase 2.
     */
    public function test_a_delivery_is_not_processed(): void
    {
        Bus::fake();

        $this->postJson(self::URL, $this->delivery())->assertOk();

        Bus::assertNothingDispatched();

        $this->assertSame(
            0,
            \DB::table('jobs')->count(),
            'A delivery queued work. This endpoint is the verification handshake only — handling '
                .'messages is Phase 3, and Phase 3 is gated on written acceptance of Phase 2.'
        );
    }

    public function test_an_unparseable_body_still_returns_200(): void
    {
        // Meta retries anything that is not 2xx and disables a subscription
        // that keeps failing. Nothing downstream needs this payload yet.
        $this->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json at all')
            ->assertOk();
    }

    // ── Signature ────────────────────────────────────────────────────────────

    public function test_a_forged_signature_is_refused_once_the_app_secret_is_set(): void
    {
        app(SettingsService::class)->set('whatsapp', 'app_secret', 'the-app-secret');

        $this->call(
            'POST', self::URL, [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef'],
            json_encode($this->delivery())
        )->assertForbidden();
    }

    public function test_a_genuine_signature_is_accepted(): void
    {
        app(SettingsService::class)->set('whatsapp', 'app_secret', 'the-app-secret');

        $body = json_encode($this->delivery());

        $this->call(
            'POST', self::URL, [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'the-app-secret'),
            ],
            $body
        )->assertOk();
    }

    /**
     * ⚠️ UNVERIFIABLE IS NOT VALID, AND THE LOG HAS TO SAY WHICH.
     *
     * Until the app secret is filled in this endpoint accepts a POST from
     * anybody. That is a deliberate, temporary trade — but it must be visible
     * in the log rather than inferred from an absent field.
     */
    public function test_an_unsigned_delivery_is_accepted_but_recorded_as_unverifiable(): void
    {
        $this->postJson(self::URL, $this->delivery())->assertOk();

        $this->assertStringContainsString('"signature":"unverifiable"', $this->logContents());
    }

    // ── The log ──────────────────────────────────────────────────────────────

    /**
     * ⚠️ PHONE NUMBERS AND MESSAGE TEXT MUST NOT LAND IN A FILE INSIDE THE WEB
     * ROOT.
     *
     * storage/ sits inside the served directory on this host, behind a single
     * .htaccess that a deploy has already deleted once. A plaintext log of
     * members' numbers and what they wrote is the worst file that could be
     * sitting there when that happens again.
     */
    public function test_the_log_does_not_contain_phone_numbers_or_message_text(): void
    {
        $this->postJson(self::URL, $this->delivery())->assertOk();

        $log = $this->logContents();

        $this->assertNotSame('', $log, 'Nothing was logged at all, so this proves nothing.');

        foreach ([
            '18765550199' => 'a member phone number',
            '18765550142' => 'our own display number',
            'Please stop sending me these messages' => 'the text of a message',
            'Aisha Mohammed' => 'a profile name',
        ] as $secret => $what) {
            $this->assertStringNotContainsString(
                $secret,
                $log,
                "The webhook log contains {$what} in plain text."
            );
        }

        // Structure survives — which is the whole point of logging at all.
        $this->assertStringContainsString('whatsapp_business_account', $log);
        $this->assertStringContainsString('wamid.HBgLMTg3NjU1NTAxOTk', $log);
        // Last four digits kept, so a delivery can still be traced to a member.
        $this->assertStringContainsString('0199', $log);
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The test above is a negative assertion over
     * a file, which is worth nothing unless the redactor demonstrably changes
     * something and demonstrably leaves the rest alone.
     */
    public function test_the_redactor_can_be_shown_working_and_not_over_reaching(): void
    {
        $webhook = app(WhatsAppWebhook::class);
        $payload = $this->delivery();

        // The control: the number really is in the input.
        $this->assertStringContainsString('18765550199', json_encode($payload));

        $redacted = json_encode($webhook->redact($payload), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('18765550199', $redacted);
        $this->assertStringContainsString('•••••••0199', $redacted);

        // And it does not simply flatten everything it touches.
        $this->assertStringContainsString('messaging_product', $redacted);
        $this->assertStringContainsString('109876543', $redacted, 'phone_number_id is an opaque ID, not a number, and must survive.');
        $this->assertStringContainsString('1754380800', $redacted);
    }

    // ── Wiring ───────────────────────────────────────────────────────────────

    public function test_the_rate_limiter_is_registered(): void
    {
        $this->assertNotNull(
            RateLimiter::limiter('whatsapp-webhook'),
            'The route asks for throttle:whatsapp-webhook and no such limiter is defined, which '
                .'throws on every delivery.'
        );
    }

    /**
     * ⚠️ THE GET MUST EXIST. The brief specified POST only; the verification
     * Meta actually performs is a GET, and a POST-only route answers 405 to it.
     */
    public function test_both_verbs_are_routed(): void
    {
        $this->storeToken();

        $this->get(self::URL.'?hub.mode=subscribe&hub.challenge=ok&hub.verify_token=a-test-verify-token')
            ->assertOk();

        $this->postJson(self::URL, [])->assertOk();
    }
}
