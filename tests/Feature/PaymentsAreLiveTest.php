<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use App\Support\Payments;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ⚠️ FOUR INPUTS, EACH WITH A VETO.
 *
 * Neither obvious single source of truth is safe. The free period alone means
 * clearing one flag renders purchase buttons against an empty merchant id.
 * Merchant credentials alone mean the client pasting his details in to save
 * them silently switches the public page to paid while the free period is
 * still running.
 *
 * Both are a public page promising something the database contradicts, which
 * is the same rule that governs the founding count.
 */
class PaymentsAreLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /**
     * ⚠️ THROUGH THE SERVICE, NOT STRAIGHT INTO THE COLUMN.
     *
     * `api_key` is a secret and is stored encrypted. Writing plaintext into
     * the row makes the decrypt fail, so the credential reads as empty and the
     * predicate vetoes for a reason that has nothing to do with the test. That
     * is exactly how this fixture failed the first time it ran.
     */
    private function set(string $group, string $key, string $value): void
    {
        app(SettingsService::class)->set($group, $key, $value);
        app(SettingsService::class)->flush();
    }

    /** Every input satisfied. The only arrangement that may answer yes. */
    private function everythingLive(): void
    {
        $this->set('general', 'free_period', '0');
        $this->set('payments', 'gateway', 'powertranz');
        $this->set('payments', 'environment', 'production');
        $this->set('payments', 'merchant_id', 'M123');
        $this->set('payments', 'terminal_id', 'T456');
        $this->set('payments', 'api_key', 'K789');
    }

    /**
     * ⚠️ THE POSITIVE CONTROL, AND IT COMES FIRST BECAUSE EVERY OTHER TEST
     * HERE ASSERTS "NOT LIVE".
     *
     * A predicate hardcoded to false satisfies all of them, and its only
     * symptom is a pricing page that never appears no matter what the client
     * configures.
     */
    public function test_with_all_four_satisfied_payments_are_live(): void
    {
        $this->everythingLive();

        $this->assertTrue(
            Payments::areLive(),
            'Every input is satisfied and the predicate still says no, so it can never say yes '
                .'and the pricing page is unreachable by configuration.',
        );
    }

    /**
     * ⚠️ EACH INPUT FLIPPED INDEPENDENTLY, FROM A FULLY LIVE STATE.
     *
     * Flipping one at a time from live is the only way to prove each has its
     * own veto rather than one of them carrying the whole answer.
     */
    #[DataProvider('vetoes')]
    public function test_any_single_dissenting_input_means_not_live(string $group, string $key, string $value, string $why): void
    {
        $this->everythingLive();
        $this->set($group, $key, $value);

        $this->assertFalse(
            Payments::areLive(),
            "Payments read as live with {$group}.{$key} set to \"{$value}\". {$why}",
        );
    }

    public static function vetoes(): array
    {
        return [
            'free period still running' => ['general', 'free_period', '1',
                'The public pages would sell something while telling members it is free.'],
            'no gateway chosen' => ['payments', 'gateway', '',
                'A purchase button with nothing behind it.'],
            'still pointing at sandbox' => ['payments', 'environment', 'sandbox',
                'A sandbox takes a real card number and settles nothing, which looks like success to everybody.'],
            'no merchant id' => ['payments', 'merchant_id', '',
                'The credential that identifies who is being paid is missing.'],
            'no terminal id' => ['payments', 'terminal_id', '',
                'The credential the gateway needs to route the transaction is missing.'],
            'no api key' => ['payments', 'api_key', '',
                'Nothing can authenticate against the gateway.'],
        ];
    }

    // ── What the public page actually renders ────────────────────────────

    public function test_the_home_page_carries_the_free_period_wording_today(): void
    {
        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Nothing, while we are getting started', $home);
    }

    /**
     * ⚠️ THE SECTION MUST FOLLOW THE PREDICATE, NOT ITS OWN OPINION.
     *
     * A section that renders the same thing whatever the settings say is the
     * bug this guard exists for, and it looks identical to a correct one until
     * the day the client switches payments on.
     */
    public function test_the_section_changes_when_the_predicate_does(): void
    {
        $before = $this->get('/')->getContent();

        $this->everythingLive();

        $after = $this->get('/')->getContent();

        $this->assertStringContainsString('Nothing, while we are getting started', $before);
        $this->assertStringNotContainsString(
            'Nothing, while we are getting started',
            $after,
            'The free-period wording is still on the page after payments went live. The section '
                .'is ignoring the predicate.',
        );
    }

    /** ⚠️ No provider is ever named to a visitor, in either state. */
    public function test_no_payment_provider_is_named_in_either_state(): void
    {
        foreach ([false, true] as $live) {
            if ($live) {
                $this->everythingLive();
            }

            $home = strtolower($this->get('/')->getContent());

            foreach (['powertranz', 'stripe', 'paypal', 'checkout.com', 'braintree'] as $provider) {
                $this->assertStringNotContainsString(
                    $provider,
                    $home,
                    "The payment provider \"{$provider}\" is named on the home page. Who processes "
                        .'a payment is our arrangement, not a selling point, and naming it tells '
                        .'anybody probing exactly what to probe.',
                );
            }
        }
    }
}
