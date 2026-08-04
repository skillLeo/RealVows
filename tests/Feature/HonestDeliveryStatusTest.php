<?php

namespace Tests\Feature;

use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE LEDGER MAY NOT CLAIM KNOWLEDGE IT DOES NOT HAVE.
 *
 * Hostinger SMTP returns a 250 at handover and reports nothing afterwards. A
 * hard bounce to a dead address, a silent drop into spam, and a message read
 * twice are indistinguishable from here — and all three look like success.
 *
 * A row that said `delivered` on the strength of a 250 would be the vacuous
 * assertion pattern at the infrastructure level: reporting success while
 * checking nothing. The difference from the six code instances is that the
 * thing lying would be an admin screen the client uses to decide whether a
 * member was told about an introduction they paid for.
 *
 * See docs/EMAIL-DELIVERABILITY.md for what it would take to lift the ceiling.
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

    /** ⚠️ An email may reach `accepted` and no further. */
    public function test_an_email_cannot_be_marked_delivered(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL);

        $this->assertTrue($email->applyStatus(MessageDelivery::STATUS_ACCEPTED));

        $this->assertFalse(
            $email->applyStatus(MessageDelivery::STATUS_DELIVERED),
            'An email was marked delivered. SMTP returns a 250 at handover and nothing '
                .'afterwards, so this is the panel inventing a fact.'
        );

        $this->assertFalse($email->applyStatus(MessageDelivery::STATUS_READ));

        $this->assertSame(MessageDelivery::STATUS_ACCEPTED, $email->fresh()->status);
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
        $this->assertStringContainsString('cannot tell', $email->uncertainty());
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
     * ⚠️ THE CEILING MUST NOT BE RAISED WITHOUT THE WEBHOOK THAT EARNS IT.
     *
     * It is one constant and it will look like a one-line improvement. This
     * test is the tripwire, and its message is where the reasoning lives.
     */
    public function test_the_email_ceiling_is_still_accepted(): void
    {
        $email = $this->delivery(MessageDelivery::CHANNEL_EMAIL);

        foreach ([MessageDelivery::STATUS_DELIVERED, MessageDelivery::STATUS_READ] as $status) {
            $fresh = $this->delivery(MessageDelivery::CHANNEL_EMAIL);

            $this->assertFalse(
                $fresh->applyStatus($status),
                "The email ceiling has been raised to allow [{$status}] without a transport that "
                    .'reports bounces. See docs/EMAIL-DELIVERABILITY.md — raising this constant '
                    .'restores exactly the lie it was added to prevent.'
            );
        }

        $this->assertTrue($email->applyStatus(MessageDelivery::STATUS_ACCEPTED));
    }
}
