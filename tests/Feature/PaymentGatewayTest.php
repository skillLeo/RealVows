<?php

namespace Tests\Feature;

use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Payments\PaymentRedactor;
use App\Services\Payments\PowerTranzGateway;
use App\Services\Payments\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Queue::fake();
        Mail::fake();
    }

    private function purchase(array $overrides = []): Purchase
    {
        $user = User::factory()->create();

        return Purchase::create([
            'user_id' => $user->id,
            'package_key' => 'triple',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'credits_granted' => 3,
            'status' => Purchase::STATUS_PENDING_3DS,
            'gateway' => 'powertranz',
            'gateway_mode' => 'sandbox',
            'order_identifier' => (string) Str::uuid(),
            'spi_token' => 'spi-test',
            ...$overrides,
        ]);
    }

    // ── Redaction ────────────────────────────────────────────────────────

    /**
     * ⚠️ Written before the gateway and run on every payload BEFORE storage,
     * not as a display filter. A display filter leaves the real number in the
     * database forever, where the next screen somebody writes will find it.
     */
    public function test_card_data_never_survives_redaction(): void
    {
        $redactor = new PaymentRedactor;

        $out = $redactor->redact([
            'CardPan' => '4111111111111111',
            'card_number' => '5555555555554444',
            'Cvv' => '123',
            'ExpiryDate' => '1229',
            'Source' => ['CardholderName' => 'A Member', 'Pan' => '4111111111111111'],
            'PowerTranzPassword' => 'super-secret',
            'TotalAmount' => 50.00,
        ]);

        $json = json_encode($out);

        $this->assertStringNotContainsString('4111111111111111', $json);
        $this->assertStringNotContainsString('5555555555554444', $json);
        $this->assertStringNotContainsString('super-secret', $json);
        $this->assertStringNotContainsString('123', (string) $out['Cvv']);

        // Still useful: last four survives so a transaction is identifiable.
        $this->assertStringContainsString('1111', $out['CardPan']);

        // Non-sensitive data is untouched.
        $this->assertSame(50.00, $out['TotalAmount']);
        $this->assertSame('A Member', $out['Source']['CardholderName']);
    }

    /** The safety net for fields nobody thought to name. */
    public function test_a_card_number_hidden_in_a_free_text_field_is_still_scrubbed(): void
    {
        $out = (new PaymentRedactor)->redact([
            'ResponseMessage' => 'Declined for card 4111 1111 1111 1111 at terminal 9',
        ]);

        $this->assertStringNotContainsString('4111 1111 1111 1111', $out['ResponseMessage']);
        $this->assertStringContainsString('1111', $out['ResponseMessage']);
    }

    // ── PowerTranz wire behaviour ────────────────────────────────────────

    /** ⚠️ USD is '840'. Sending 'USD' fails validation with an unhelpful error. */
    public function test_the_currency_is_sent_as_an_iso_numeric_string(): void
    {
        Http::fake(['*' => Http::response(['Approved' => true, 'SpiToken' => 'x'], 200)]);

        app(PowerTranzGateway::class)->initiate($this->purchase(), 'https://realvows.test/cb');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            $this->assertSame('840', $body['CurrencyCode'], "Currency must be ISO numeric '840', not 'USD'.");

            return true;
        });
    }

    public function test_an_idempotency_key_is_sent_and_is_stable_across_retries(): void
    {
        Http::fake(['*' => Http::response(['Approved' => true], 200)]);

        $purchase = $this->purchase();
        $gateway = app(PowerTranzGateway::class);

        $gateway->initiate($purchase, 'https://realvows.test/cb');
        $gateway->initiate($purchase, 'https://realvows.test/cb');

        $keys = [];

        Http::assertSent(function ($request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0] ?? null;

            return true;
        });

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1], 'A retry must carry the same key or it can charge twice.');
        $this->assertNotEmpty($keys[0]);
    }

    public function test_the_credentials_come_from_settings_and_not_the_environment(): void
    {
        Http::fake(['*' => Http::response(['Approved' => true], 200)]);

        app(\App\Services\Settings\SettingsService::class);
        \App\Models\Setting::where('group', 'payments')->where('key', 'merchant_id')->update(['value' => 'MERCHANT-123']);
        app(\App\Services\Settings\SettingsService::class)->flush();

        app(PowerTranzGateway::class)->initiate($this->purchase(), 'https://realvows.test/cb');

        Http::assertSent(fn ($request) => $request->header('PowerTranz-PowerTranzId')[0] === 'MERCHANT-123');
    }

    public function test_sandbox_and_live_differ_only_by_host(): void
    {
        Http::fake(['*' => Http::response(['Approved' => true], 200)]);

        $gateway = app(PowerTranzGateway::class);
        $this->assertSame('sandbox', $gateway->mode());

        $gateway->initiate($this->purchase(), 'https://realvows.test/cb');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'staging.ptranz.com'));

        \App\Models\Setting::where('group', 'payments')->where('key', 'environment')->update(['value' => 'live']);
        app(\App\Services\Settings\SettingsService::class)->flush();

        $live = app(PowerTranzGateway::class);
        $this->assertSame('live', $live->mode());

        $live->initiate($this->purchase(), 'https://realvows.test/cb');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'gateway.ptranz.com'));
    }

    /**
     * ⚠️ A network failure is NOT a decline. Treating it as one would strand
     * a member who has genuinely paid, so it stays pending and reconciliation
     * asks again later.
     */
    public function test_an_unreachable_gateway_leaves_the_purchase_pending_rather_than_failed(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $status = app(PowerTranzGateway::class)->query($this->purchase());

        $this->assertSame(Purchase::STATUS_PENDING_3DS, $status->status);
        $this->assertFalse($status->isApproved());
    }

    public function test_a_timeout_is_recorded_as_its_own_event(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $purchase = $this->purchase();
        app(PowerTranzGateway::class)->query($purchase);

        $this->assertDatabaseHas('payment_events', [
            'purchase_id' => $purchase->id,
            'note' => 'Connection failed or timed out.',
        ]);
    }

    public function test_every_gateway_exchange_is_logged_in_both_directions(): void
    {
        Http::fake(['*' => Http::response(['Approved' => true, 'TransactionIdentifier' => 'T1'], 200)]);

        $purchase = $this->purchase();
        app(PowerTranzGateway::class)->query($purchase);

        $events = PaymentEvent::where('purchase_id', $purchase->id)->get();

        $this->assertTrue($events->contains('direction', 'outbound'));
        $this->assertTrue($events->contains('direction', 'inbound'));
    }

    public function test_a_logged_payload_never_contains_card_data(): void
    {
        Http::fake(['*' => Http::response([
            'Approved' => false,
            'IsoResponseCode' => '05',
            'ResponseMessage' => 'Declined',
            'Pan' => '4111111111111111',
        ], 200)]);

        $purchase = $this->purchase();
        app(PowerTranzGateway::class)->query($purchase);

        foreach (PaymentEvent::where('purchase_id', $purchase->id)->get() as $event) {
            $this->assertStringNotContainsString('4111111111111111', json_encode($event->payload));
        }
    }

    // ── The callback ─────────────────────────────────────────────────────

    /**
     * ⚠️ THE ROUTE THAT PASSES ON LOCALHOST AND FAILS LIVE IF ANYONE MOVES IT.
     *
     * The 3DS return is a cross-site POST with no session cookie. This proves
     * the route works for a caller with no session at all.
     */
    public function test_the_callback_works_with_no_session_and_no_csrf_token(): void
    {
        $purchase = $this->purchase(['amount_cents' => 5000]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('checkout.callback', [
            'order' => $purchase->order_identifier,
        ]);

        // No actingAs. No session. Exactly what the bank sends.
        $response = $this->post($url, ['SpiToken' => 'spi-test']);

        $response->assertRedirect();
        $this->assertSame(3, $purchase->user->fresh()->creditBalance());
    }

    public function test_the_callback_is_not_in_the_session_middleware_group(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'checkout.callback');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains(
            \Illuminate\Session\Middleware\StartSession::class,
            $middleware,
            'The callback has picked up session middleware. It will pass locally and fail on the live domain.'
        );

        $this->assertContains('signed', $route->middleware());
    }

    public function test_a_callback_for_an_unknown_order_answers_200_and_does_nothing(): void
    {
        $url = \Illuminate\Support\Facades\URL::signedRoute('checkout.callback', [
            'order' => (string) Str::uuid(),
        ]);

        // 200 rather than an error: arguing with a payment gateway earns
        // retries, and there is genuinely nothing to do.
        $this->post($url, ['SpiToken' => 'nope'])->assertOk();

        $this->assertSame(0, \App\Models\CreditLedgerEntry::count());
    }

    public function test_an_unsigned_callback_is_rejected(): void
    {
        $purchase = $this->purchase();

        $this->post(route('checkout.callback', ['order' => $purchase->order_identifier]), [])
            ->assertForbidden();

        $this->assertSame(0, $purchase->user->fresh()->creditBalance());
    }

    /**
     * ⚠️ The callback body is never trusted. A POST claiming success for a
     * purchase the gateway reports as declined must credit nothing.
     */
    public function test_a_callback_claiming_success_cannot_override_the_gateway(): void
    {
        // FakeGateway declines anything ending 01.
        $purchase = $this->purchase(['amount_cents' => 5001]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('checkout.callback', [
            'order' => $purchase->order_identifier,
        ]);

        $this->post($url, ['Approved' => 'true', 'ResponseCode' => '00', 'TransactionIdentifier' => 'forged']);

        $this->assertSame(0, $purchase->user->fresh()->creditBalance());
        $this->assertSame(Purchase::STATUS_FAILED, $purchase->fresh()->status);
    }

    public function test_the_callback_is_recorded_as_a_signal_not_as_evidence(): void
    {
        $purchase = $this->purchase();

        $url = \Illuminate\Support\Facades\URL::signedRoute('checkout.callback', [
            'order' => $purchase->order_identifier,
        ]);

        $this->post($url, ['SpiToken' => 'spi-test']);

        $this->assertDatabaseHas('payment_events', [
            'purchase_id' => $purchase->id,
            'endpoint' => 'callback',
            'note' => 'Signal only — status re-queried from the gateway.',
        ]);
    }

    // ── Reconciliation ───────────────────────────────────────────────────

    /** The job that saves a member whose callback was lost in transit. */
    public function test_reconciliation_settles_a_purchase_whose_callback_never_arrived(): void
    {
        $purchase = $this->purchase(['created_at' => now()->subHour()]);

        app(\App\Jobs\ReconcilePendingPayments::class)->handle(app(PurchaseService::class));

        $this->assertSame(Purchase::STATUS_COMPLETED, $purchase->fresh()->status);
        $this->assertSame(3, $purchase->user->fresh()->creditBalance());
    }

    /**
     * A member may simply be sitting on their bank's 3DS screen. Re-querying
     * underneath them would be wrong.
     */
    public function test_reconciliation_leaves_a_very_recent_purchase_alone(): void
    {
        $purchase = $this->purchase(['created_at' => now()->subMinutes(2)]);

        app(\App\Jobs\ReconcilePendingPayments::class)->handle(app(PurchaseService::class));

        $this->assertSame(Purchase::STATUS_PENDING_3DS, $purchase->fresh()->status);
    }

    public function test_reconciliation_gives_up_on_an_ancient_purchase(): void
    {
        $purchase = $this->purchase(['created_at' => now()->subDays(5)]);

        app(\App\Jobs\ReconcilePendingPayments::class)->handle(app(PurchaseService::class));

        $this->assertSame(Purchase::STATUS_FAILED, $purchase->fresh()->status);
        $this->assertSame('timeout', $purchase->fresh()->failure_code);
        $this->assertSame(0, $purchase->user->fresh()->creditBalance());
    }
}
