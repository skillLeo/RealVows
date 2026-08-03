<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE COMPANY TAKING THE MONEY IS NOT THE NAME ON THE STATEMENT.
 *
 * "RealVows" could not be registered as a company in time, so the gateway
 * account sits under a business the client already controls, with RealVows as
 * the trading name. The money settles to one company; the payer sees another
 * name when their card statement arrives.
 *
 * An unrecognised name on a bank statement is the single commonest cause of a
 * chargeback, so the receipt, the receipt email and the purchase history all
 * have to state the descriptor plainly — and none of them may hardcode it,
 * because the entity is not confirmed yet and will change again when RealVows
 * is registered in its own right.
 *
 * This replaced a constant class that named one specific company. That was
 * correct for exactly as long as that company was the one settling, and would
 * have gone silently wrong the day it was not.
 */
class BillingIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /** Every file that can put a billing identity in front of a member. */
    private function billingSurfaces(): array
    {
        return [
            'receipt' => resource_path('views/receipt.blade.php'),
            'receipt email' => resource_path('views/emails/purchase-receipt.blade.php'),
            'purchase history' => resource_path('js/Pages/Purchases.vue'),
            'receipt controller' => app_path('Http/Controllers/ReceiptController.php'),
            'purchases controller' => app_path('Http/Controllers/PurchasesController.php'),
            'receipt mailable' => app_path('Mail/PurchaseReceipt.php'),

            /*
             * ⚠️ THE LEGAL PAGES COUNT AS BILLING SURFACES.
             *
             * The Terms state who a member is contracting with; the statement
             * states who took the money. If those two disagree it is a
             * mismatch an acquirer's underwriting will flag — so they must
             * read the same setting, and neither may hardcode a name.
             */
            'legal pages' => resource_path('views/public/legal.blade.php'),
            'legal controller' => app_path('Http/Controllers/LegalController.php'),
        ];
    }

    /**
     * ⚠️ THE CHECK THE BRIEF ASKED FOR: no company name anywhere.
     *
     * The list is every entity that has been, or has been proposed as, the
     * settling company on this project. Any of them appearing in a billing
     * surface means somebody has hardcoded what must come from settings.
     */
    public function test_no_company_name_is_hardcoded_in_any_billing_surface(): void
    {
        $forbidden = [
            'SkillLeo',
            'Skill Leo',
            'SKILLLEO',
            'Gemini Security',
            'Gemini',
            'SMC (Pvt)',
        ];

        $offences = [];

        foreach ($this->billingSurfaces() as $what => $path) {
            $contents = File::get($path);

            foreach ($forbidden as $name) {
                if (str_contains($contents, $name)) {
                    $offences[] = "{$what} hardcodes \"{$name}\"";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A company name is hardcoded in a billing surface:\n  ".implode("\n  ", $offences)
                ."\n\nThe settling entity is not confirmed and will change again once RealVows is "
                .'registered in its own right. Read it from the payments settings group.'
        );
    }

    /** The constant class this replaced must not come back. */
    public function test_the_hardcoded_billing_constant_is_gone(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Support/SkillLeo.php'),
            'The hardcoded billing constant is back. The entity that settles the money is a '
                .'setting, because it is not yet confirmed and will change.'
        );
    }

    /** All five keys exist in the payments group, and ship empty. */
    public function test_the_five_billing_fields_exist_and_are_deliberately_empty(): void
    {
        foreach (Billing::KEYS as $key) {
            $setting = Setting::where('group', 'payments')->where('key', $key)->first();

            $this->assertNotNull(
                $setting,
                "The payments settings group has no `{$key}`, so the client cannot fill it in."
            );
        }

        /*
         * ⚠️ Empty is the correct state TODAY and this assertion is expected
         * to be updated the day the client confirms the entity with his bank.
         * It is here so that filling them in is a deliberate act with a test
         * change attached, rather than something that drifts in.
         */
        $billing = app(Billing::class);

        $this->assertFalse(
            $billing->isConfigured(),
            'The billing identity has been filled in. If the client has confirmed the entity '
                .'with his bank that is correct — update this test to assert the real values. '
                .'If not, somebody has guessed at who is holding the money.'
        );
    }

    /**
     * ⚠️ AN UNCONFIGURED RECEIPT NAMES NO COMPANY, BUT STILL NAMES THE
     * DESCRIPTOR — AND THE DIFFERENCE MATTERS.
     *
     * Naming a company that has not been confirmed is a false statement about
     * who is holding somebody's money. Naming the descriptor is not a claim
     * about anybody: it is telling the member which string will appear on
     * their statement, which is true as soon as the gateway is configured to
     * send it and is the only fact that prevents a chargeback.
     *
     * So the descriptor ships with a default and the entity does not.
     */
    public function test_an_unconfigured_receipt_names_no_company(): void
    {
        $billing = app(Billing::class);

        $this->assertFalse($billing->isConfigured());
        $this->assertNull($billing->legalEntity(), 'A company is being named before the bank confirmed one.');
        $this->assertNull($billing->tradingAsLine());

        // But the member is still told what to look for.
        $this->assertSame('REALVOWS', $billing->statementDescriptor());
        $this->assertSame(
            'This charge appears on your statement as REALVOWS.',
            $billing->statementLine()
        );
    }

    /**
     * ⚠️ THE DESCRIPTOR MUST BE WHAT CARD NETWORKS ACCEPT, and the default
     * has to satisfy its own rule or it is a trap for the first person who
     * saves the settings form without touching it.
     */
    public function test_the_default_descriptor_satisfies_the_card_network_rules(): void
    {
        $descriptor = app(Billing::class)->statementDescriptor();

        $this->assertNotNull($descriptor);
        $this->assertLessThanOrEqual(22, strlen($descriptor), 'Card networks truncate past 22.');
        $this->assertMatchesRegularExpression(
            '/^[A-Z0-9][A-Z0-9 ]*$/',
            $descriptor,
            'Lowercase or punctuation in a descriptor fails during acquirer underwriting, days '
                .'later, as a stalled application nobody connects back to this field.'
        );
    }

    /**
     * ⚠️ THE SENTENCE THAT PREVENTS CHARGEBACKS, once configured.
     *
     * It must name the DESCRIPTOR, not the company — the member is not being
     * asked to follow the corporate structure, only told which string to look
     * for on their statement.
     */
    public function test_a_configured_receipt_states_the_descriptor_in_plain_words(): void
    {
        $this->configure([
            'billing_legal_entity' => 'Example Holdings Limited',
            'billing_trading_name' => 'RealVows',
            'statement_descriptor' => 'REALVOWS',
            'billing_support_email' => 'billing@example.test',
        ]);

        $billing = app(Billing::class);

        $this->assertTrue($billing->isConfigured());

        $this->assertSame(
            'This charge appears on your statement as REALVOWS.',
            $billing->statementLine(),
            'The line must name the descriptor, which is the only fact that stops somebody '
                .'phoning their bank about a name they do not recognise.'
        );

        $this->assertSame('Example Holdings Limited, trading as RealVows', $billing->tradingAsLine());
    }

    /**
     * ⚠️ "RealVows, trading as RealVows" is noise that makes a receipt look
     * automated. Once the product is registered in its own right the two
     * names converge, and the line must disappear on its own.
     */
    public function test_the_trading_as_line_disappears_when_the_names_converge(): void
    {
        $this->configure([
            'billing_legal_entity' => 'RealVows',
            'billing_trading_name' => 'RealVows',
            'statement_descriptor' => 'REALVOWS',
        ]);

        $this->assertNull(
            app(Billing::class)->tradingAsLine(),
            'The receipt would read "RealVows, trading as RealVows".'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every assertion above is a scan or a
     * negative, so the reader must be shown finding a real offence.
     */
    public function test_the_scan_can_see_a_hardcoded_company_name(): void
    {
        // The exact shape of the code this replaced.
        $offending = "<div class=\"billed\"><strong>SkillLeo SMC (Pvt) Ltd</strong></div>";

        $found = [];

        foreach (['SkillLeo', 'Gemini Security', 'SMC (Pvt)'] as $name) {
            if (str_contains($offending, $name)) {
                $found[] = $name;
            }
        }

        $this->assertNotEmpty(
            $found,
            'The scan cannot recognise a hardcoded company name, so it proves nothing about the '
                .'real billing surfaces.'
        );

        // And the surface list must not be empty, or the loop never runs.
        $this->assertNotEmpty($this->billingSurfaces());

        foreach ($this->billingSurfaces() as $what => $path) {
            $this->assertFileExists($path, "The {$what} surface has moved; the scan is not reading it.");
        }
    }

    /**
     * ⚠️ THE CONTRACT AND THE STATEMENT MUST NAME THE SAME COMPANY.
     *
     * Two independent surfaces, one source. This is the check that would
     * catch somebody "helpfully" typing the entity into the Terms while the
     * receipt still reads it from settings.
     */
    public function test_the_legal_pages_and_the_receipt_name_the_same_entity(): void
    {
        $this->configure([
            'billing_legal_entity' => 'Example Holdings Limited',
            'billing_trading_name' => 'RealVows',
            'statement_descriptor' => 'REALVOWS',
            'billing_address' => '1 Example Way, Kingston',
            'billing_support_email' => 'billing@example.test',
        ]);

        foreach (['/terms', '/privacy', '/community-guidelines'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Example Holdings Limited', false)
                ->assertSee('billing@example.test', false);
        }
    }

    /**
     * ⚠️ AND WHILE UNCONFIRMED, THE CONTRACT NAMES NOBODY.
     *
     * A member agreeing to terms with a company that turns out not to be the
     * one holding their money is worse than a page that admits it is pending.
     */
    public function test_the_legal_pages_name_nobody_until_the_entity_is_confirmed(): void
    {
        $response = $this->get('/terms')->assertOk();

        $response->assertSee('the contracting entity is not yet confirmed', false);
        $response->assertDontSee('Who you are contracting with', false);
    }

    private function configure(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = Setting::where('group', 'payments')->where('key', $key)->firstOrFail();
            $setting->setPlainValue($value);
            $setting->save();
        }

        app(\App\Services\Settings\SettingsService::class)->flush();
    }
}
