<?php

namespace Tests\Feature;

use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE LEDGER MAY NOT CLAIM KNOWLEDGE IT DOES NOT HAVE.
 *
 * Each channel may claim exactly what its provider tells us, and no more.
 *
 * ⚠️ THIS FILE CHANGED WHEN RESEND LANDED, AND THAT IS THE POINT OF IT.
 *
 * Under bare SMTP a 250 at handover was the last fact available: a hard bounce
 * and a message read twice were indistinguishable, and both looked like
 * success. Email therefore stopped at `accepted`.
 *
 * Resend posts back delivered, bounced and complained, so `delivered` became a
 * fact we are TOLD rather than one we assume, and the ceiling rose to match.
 * The tests were rewritten rather than deleted — a guard left asserting a
 * condition that has stopped applying fires on something correct, somebody
 * overrides it, and the habit of overriding guards is what gets established.
 *
 * What remains unknowable is `read`, and that is a decision rather than a
 * limit: it needs a tracking pixel, and this product sends no images at all.
 */
class HonestDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(string $channel, string $status = MessageDelivery::STATUS_QUEUED): MessageDelivery
    {
        return MessageDelivery::create([
            'user_id' => User::factory()->create()->id,
            'channel' => $channel,
            'template' => 'introduction_delivered',
            'status' => $status,
        ]);
    }

    // ── The ceiling ──────────────────────────────────────────────────────────

    /**
     * ⚠️ AN EMAIL MAY NOW REACH `delivered`, AND MUST STILL NEVER REACH `read`.
     *
     * This test used to assert the ceiling was `accepted`. That stopped being
     * true on 5 August 2026 when the Resend webhook landed and earned the
     * raise — Resend posts back delivery confirmations, so `delivered` is a
     * fact we are told rather than one we assume.
     *
     * ⚠️ THE TEST WAS REWRITTEN RATHER THAN DELETED, because the remaining
     * half is the one that still matters. A guard left asserting a condition
     * that no longer applies is its own failure: it fires on something
     * correct, somebody overrides it, and the habit of overriding guards is
     * what gets established.
     *
     * `read` requires a tracking pixel. This product sends no images at all —
     * an inbox is a preview pane on a shared laptop and a lock screen at a
     * family dinner — so we do not know when an email was opened and are not
     * going to start knowing.
     */
    public function test_an_email_can_reach_delivered_but_never_read(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL);

        $this->assertTrue($email->applyStatus(MessageDelivery::STATUS_ACCEPTED));

        $this->assertTrue(
            $email->applyStatus(MessageDelivery::STATUS_DELIVERED),
            'The email ceiling is still at accepted even though the Resend webhook is live, so '
                .'the ledger is refusing a fact the provider is telling us.'
        );

        $this->assertFalse(
            $email->applyStatus(MessageDelivery::STATUS_READ),
            'An email was marked read. That requires a tracking pixel, and this product sends no '
                .'images — so "read" would be an invented fact.'
        );

        $this->assertSame(MessageDelivery::STATUS_DELIVERED, $email->fresh()->status);
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The test above passes just as well against
     * a ladder that refuses every transition, which would freeze WhatsApp at
     * `queued` forever and hide real delivery information.
     */
    public function test_whatsapp_can_still_reach_read(): void
    {
        $whatsapp = $this->delivery(MessageDelivery::CHANNEL_WHATSAPP);

        $this->assertTrue($whatsapp->applyStatus(MessageDelivery::STATUS_SENT));
        $this->assertTrue($whatsapp->applyStatus(MessageDelivery::STATUS_DELIVERED));
        $this->assertTrue($whatsapp->applyStatus(MessageDelivery::STATUS_READ));

        $this->assertSame(MessageDelivery::STATUS_READ, $whatsapp->fresh()->status);
    }

    /**
     * ⚠️ A KNOWN FAILURE IS REAL INFORMATION AND IS NEVER SUPPRESSED.
     *
     * The ceiling exists to stop us claiming unknowable successes. Applying it
     * to failures would be the opposite mistake — hiding a bounce we actually
     * learned about, to protect a rule about things we cannot learn.
     */
    public function test_an_email_can_always_be_marked_failed(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL, MessageDelivery::STATUS_ACCEPTED);

        $this->assertTrue($email->applyStatus(MessageDelivery::STATUS_FAILED));
        $this->assertSame(MessageDelivery::STATUS_FAILED, $email->fresh()->status);
    }

    // ── What an administrator reads ──────────────────────────────────────────

    /**
     * ⚠️ THE WORD "SENT" IS THE ONE THAT WOULD RESTORE THE LIE.
     *
     * It reads as harmless and it is precisely what a member would understand
     * as "we told them", which is the claim we cannot support.
     */
    public function test_an_accepted_email_is_never_labelled_sent_or_delivered(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL, MessageDelivery::STATUS_ACCEPTED);

        $label = $email->label();

        $this->assertSame('Handed to the mail server', $label);
        $this->assertStringNotContainsStringIgnoringCase('sent', $label);
        $this->assertStringNotContainsStringIgnoringCase('delivered', $label);
    }

    public function test_a_whatsapp_delivery_is_labelled_with_what_meta_told_us(): void
    {
        $whatsapp = $this->delivery(MessageDelivery::CHANNEL_WHATSAPP, MessageDelivery::STATUS_DELIVERED);

        $this->assertSame('Delivered to their phone', $whatsapp->label());
    }

    /**
     * ⚠️ THE CAVEAT TRAVELS WITH THE ROW.
     *
     * Left to a README, nobody reads it at the moment they are looking at a
     * green row and deciding whether a member was told.
     */
    public function test_an_accepted_email_carries_its_uncertainty(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL, MessageDelivery::STATUS_ACCEPTED);

        $this->assertNotNull($email->uncertainty());
        $this->assertStringContainsString('not yet confirmed delivered', $email->uncertainty());
    }

    /** And a status that means what it says carries no caveat. */
    public function test_a_status_that_is_a_fact_carries_no_caveat(): void
    {
        $this->assertNull(
            $this->delivery(MessageDelivery::CHANNEL_WHATSAPP, MessageDelivery::STATUS_READ)->uncertainty(),
        );

        $this->assertNull(
            $this->delivery(MessageDelivery::CHANNEL_EMAIL, MessageDelivery::STATUS_FAILED)->uncertainty(),
        );
    }

    /**
     * ⚠️ THE TRIPWIRE, POINTED AT WHAT IS STILL UNKNOWABLE.
     *
     * It used to guard the whole ceiling. Now it guards the half the provider
     * cannot tell us: opens. Raising it further would need a tracking pixel,
     * and the reason there is none is a decision about members rather than a
     * technical limit — an email is read in a preview pane on a shared laptop.
     */
    public function test_the_email_ceiling_stops_at_delivered(): void
    {
        $fresh = $this->delivery(MessageDelivery::CHANNEL_EMAIL);

        $this->assertFalse(
            $fresh->applyStatus(MessageDelivery::STATUS_READ),
            'The email ceiling has been raised to allow "read". That requires a tracking pixel, '
                .'and this product deliberately sends no images at all — so this would be the '
                .'panel inventing a fact about a member.'
        );

        $this->assertTrue($this->delivery(MessageDelivery::CHANNEL_EMAIL)->applyStatus(MessageDelivery::STATUS_DELIVERED));
    }
}
