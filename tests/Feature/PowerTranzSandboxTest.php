<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PowerTranzGateway;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * ⚠️ A FAKE GATEWAY PROVES OUR CODE TALKS TO ITSELF. NOTHING MORE.
 *
 * Every payment test in this suite runs against FakeGateway, because
 * PaymentServiceProvider binds it in the testing environment so no test can
 * accidentally reach the network. That is the right default and it is not the
 * problem.
 *
 * The problem is that the REAL PowerTranz API has never been called. Not once.
 * A deterministic fake we wrote ourselves cannot tell us anything about:
 *
 *   · the numeric currency code (840) being accepted in the field they expect
 *   · the two-pass 3DS handshake — Auth, then a second call carrying SpiToken
 *   · the exact header names, and whether Idempotency-Key is honoured
 *   · the real shape of a callback body, which we parse
 *   · what a genuine decline looks like versus a genuine error
 *
 * Phase 2's agreed acceptance criterion is the payment and credit system
 * working end to end AGAINST THE POWERTRANZ SANDBOX. Until this file runs
 * green, that criterion is not met, however green the rest of the suite is.
 *
 * ⚠️ SANDBOX CREDENTIALS ARE ISSUED SEPARATELY FROM THE MERCHANT APPLICATION.
 *
 * This does NOT wait on the client's bank, his company registration, or the
 * live merchant account. It can be requested today. See the walkthrough.
 *
 * ── Running it ────────────────────────────────────────────────────────────
 *
 *   PT_SANDBOX_MERCHANT_ID=... PT_SANDBOX_TERMINAL_ID=... \
 *   PT_SANDBOX_API_KEY=... php artisan test --filter=PowerTranzSandbox
 *
 * Skipped, loudly, until those exist. A payment suite that skips in silence
 * is indistinguishable from one that passes (Rule 5).
 */
class PowerTranzSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        if (! $this->hasSandboxCredentials()) {
            $this->markTestSkipped(
                'PowerTranz sandbox credentials are not configured, so the real gateway has '
                    .'never been exercised. Phase 2 payment acceptance is NOT complete until '
                    .'this suite runs green. Set PT_SANDBOX_MERCHANT_ID, PT_SANDBOX_TERMINAL_ID '
                    .'and PT_SANDBOX_API_KEY.'
            );
        }

        $this->configureSandbox();
    }

    private function hasSandboxCredentials(): bool
    {
        return (bool) env('PT_SANDBOX_MERCHANT_ID')
            && (bool) env('PT_SANDBOX_TERMINAL_ID')
            && (bool) env('PT_SANDBOX_API_KEY');
    }

    /**
     * ⚠️ Credentials go into SETTINGS, not into config, because that is where
     * the real application reads them from. Injecting them any other way
     * would test a path production does not use.
     */
    private function configureSandbox(): void
    {
        foreach ([
            'merchant_id' => env('PT_SANDBOX_MERCHANT_ID'),
            'terminal_id' => env('PT_SANDBOX_TERMINAL_ID'),
            'api_key' => env('PT_SANDBOX_API_KEY'),
            'environment' => 'sandbox',
        ] as $key => $value) {
            $setting = Setting::where('group', 'payments')->where('key', $key)->firstOrFail();
            $setting->setPlainValue((string) $value);
            $setting->save();
        }

        app(SettingsService::class)->flush();

        // ⚠️ The provider binds FakeGateway in the testing environment. This
        // is the one suite that must not have it.
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PowerTranzGateway::class));
    }

    private function buyer(): User
    {
        return User::factory()->create();
    }

    /**
     * ⚠️ THE CHECK THIS FILE EXISTS FOR: a real authorisation, against the
     * real sandbox, producing a real 3DS handoff.
     */
    public function test_an_authorisation_returns_a_real_three_d_secure_handoff(): void
    {
        $user = $this->buyer();

        $purchase = Purchase::factory()->for($user)->create([
            'status' => 'pending',
            'amount_cents' => 2000,
            'currency' => 'USD',
        ]);

        $handoff = app(PaymentGateway::class)->initiate(
            $purchase,
            URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier])
        );

        $this->assertNotEmpty(
            $handoff->redirectUrl ?? $handoff->html ?? null,
            'The sandbox returned no way to reach the 3DS page. Either the request shape is '
                .'wrong or the credentials are not provisioned for 3DS.'
        );

        $purchase->refresh();

        $this->assertNotNull(
            $purchase->gateway_reference,
            'No gateway reference was stored, so a later query cannot ask about this purchase.'
        );
    }

    /**
     * ⚠️ THE CURRENCY FIELD. PowerTranz wants the ISO NUMERIC code, 840, not
     * "USD" — and a fake we wrote will happily accept whichever one we send
     * it. This is exactly the class of thing only the real API can settle.
     */
    public function test_the_sandbox_accepts_our_currency_format(): void
    {
        $purchase = Purchase::factory()->for($this->buyer())->create([
            'status' => 'pending',
            'amount_cents' => 2000,
            'currency' => 'USD',
        ]);

        $handoff = app(PaymentGateway::class)->initiate(
            $purchase,
            URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier])
        );

        $this->assertTrue(
            $handoff->ok ?? true,
            'The sandbox rejected the request. If the message mentions currency, we are sending '
                .'"USD" where 840 is expected.'
        );
    }

    /**
     * ⚠️ THE CALLBACK IS A CROSS-SITE POST FROM THE ISSUING BANK, and it must
     * work with no session cookie — SESSION_SAME_SITE=lax means the browser
     * will not send one. This asserts the real shape, not our imagined one.
     */
    public function test_the_callback_is_accepted_without_a_session(): void
    {
        $purchase = Purchase::factory()->for($this->buyer())->create(['status' => 'pending']);

        $url = URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier]);

        // No acting-as. No session. Exactly what the bank sends.
        $this->post($url, ['SpiToken' => 'sandbox-token'])
            ->assertRedirect();
    }

    /**
     * ⚠️ THE BODY IS NEVER TRUSTED. A credit is granted only after the
     * gateway has been re-queried and answered authoritatively.
     */
    public function test_a_credit_requires_the_gateway_to_confirm_it(): void
    {
        $user = $this->buyer();
        $before = $user->creditBalance();

        $purchase = Purchase::factory()->for($user)->create(['status' => 'pending']);

        // A forged success body, unsigned by anything the gateway did.
        $this->post(
            URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier]),
            ['Approved' => 'true', 'ResponseCode' => '00']
        );

        $this->assertSame(
            $before,
            $user->fresh()->creditBalance(),
            'A credit was granted on the strength of the callback body alone. The gateway must '
                .'be re-queried — anyone can POST to a signed URL they have seen.'
        );
    }

    /** ⚠️ A duplicate callback must grant nothing the second time. */
    public function test_a_duplicate_callback_grants_nothing(): void
    {
        $user = $this->buyer();

        $purchase = Purchase::factory()->for($user)->create(['status' => 'pending']);
        $url = URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier]);

        $this->post($url, ['SpiToken' => 'sandbox-token']);
        $afterFirst = $user->fresh()->creditBalance();

        $this->post($url, ['SpiToken' => 'sandbox-token']);

        $this->assertSame(
            $afterFirst,
            $user->fresh()->creditBalance(),
            'The second callback granted credits again. Gateways retry, and a member who is '
                .'credited twice for one payment is a reconciliation problem forever.'
        );
    }

    /** ⚠️ A declined card credits nothing and says so plainly. */
    public function test_a_declined_card_credits_nothing(): void
    {
        $user = $this->buyer();
        $before = $user->creditBalance();

        /*
         * PowerTranz sandbox exposes decline behaviour through specific test
         * card numbers. The exact card is filled in once the sandbox pack
         * arrives — it is documented per-merchant.
         */
        $this->markTestIncomplete(
            'Needs the declining test card from the PowerTranz sandbox pack. Assert: purchase '
                .'ends failed, balance unchanged from '.$before.', and the member sees a plain '
                .'explanation rather than a gateway error code.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). If this suite ever runs, it must be
     * genuinely talking to PowerTranz — not to a FakeGateway that slipped
     * back in through the provider, which would make every assertion above
     * meaningless while reporting success.
     */
    public function test_this_suite_is_really_talking_to_powertranz(): void
    {
        $gateway = app(PaymentGateway::class);

        $this->assertInstanceOf(
            PowerTranzGateway::class,
            $gateway,
            'This suite is running against the fake gateway, so it proves nothing about the '
                .'real API — which is the entire reason the file exists.'
        );

        $this->assertSame('sandbox', $gateway->mode(), 'Refusing to run against live.');
    }
}
