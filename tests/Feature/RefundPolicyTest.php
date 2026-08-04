<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE PAGE THAT WAS ENTIRELY ABSENT.
 *
 * A payment acquirer's underwriting looks for a refund and cancellation policy
 * by name. Its absence is a real gap regardless of when payments switch on —
 * and it matters MORE during the free period, not less, because members joining
 * now need to know what happens to introductions they were given free when
 * charging begins.
 */
class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    public function test_the_refund_policy_is_reachable_without_signing_in(): void
    {
        $this->get('/refunds')
            ->assertOk()
            ->assertSee('Refunds and Cancellation');
    }

    public function test_it_is_linked_from_the_public_footer(): void
    {
        $this->get('/')->assertOk()->assertSee(route('legal.refunds'));
    }

    /**
     * ⚠️ THE PROMISE THAT MUST APPEAR IN WRITING.
     *
     * Members joining free are told, on a page they can point at later, that
     * what they were given stays theirs. A promise made only in a settings
     * flag and a test is not a promise a member can hold us to.
     */
    public function test_it_states_that_free_introductions_are_kept_permanently(): void
    {
        $body = $this->get('/refunds')->getContent();

        $this->assertStringContainsString('yours to keep permanently', $body);
        $this->assertStringContainsString('never be charged for it afterwards', $body);
    }

    /** ⚠️ And it is honest about what cannot be refunded. */
    public function test_it_is_honest_that_an_opened_introduction_is_spent(): void
    {
        $body = $this->get('/refunds')->getContent();

        $this->assertStringContainsString('not normally refundable', $body);
        // …but names the exceptions, so it is a policy rather than a refusal.
        $this->assertStringContainsString('fake', $body);
        $this->assertStringContainsString('return the credit', $body);
    }

    /**
     * ⚠️ THE DRAFT BANNER STAYS UNTIL THE CLIENT APPROVES THE WORDING.
     *
     * A page that reads as finished is a page nobody checks. These are our
     * words, not a lawyer's.
     */
    public function test_it_still_declares_itself_a_draft(): void
    {
        $this->get('/refunds')->assertSee('[DRAFT', false);
    }

    /** ⚠️ It names the contracting party from settings, never hardcoded. */
    public function test_it_names_no_company_until_the_entity_is_confirmed(): void
    {
        $this->get('/refunds')->assertDontSee('Limited');

        app(SettingsService::class)->set('payments', 'billing_legal_entity', 'Gemini Security Limited');
        app(SettingsService::class)->set('payments', 'statement_descriptor', 'REALVOWS');

        $this->get('/refunds')->assertSee('Gemini Security Limited');
    }
}
