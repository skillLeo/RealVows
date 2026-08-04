<?php

namespace Tests\Feature;

use App\Jobs\FallBackToEmail;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\WhatsApp\DeliveryDispatcher;
use App\Services\WhatsApp\WhatsAppResult;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * ⚠️ THE LEDGER IS THE ONLY THING THAT CAN ANSWER "DID THEY EVER ACTUALLY
 * HEAR ABOUT THE INTRODUCTION THEY PAID FOR?"
 *
 * A member who misses an introduction has lost money. Every failure in this
 * file is invisible from the member's side — a message that was never sent
 * and a message that was sent look identical to somebody waiting for one.
 */
class MessageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function member(string $phone = '8765550199'): User
    {
        return User::factory()->create([
            'phone_country_code' => '+1',
            'phone_number' => $phone,
        ]);
    }

    private function dispatcherReturning(WhatsAppResult $result): DeliveryDispatcher
    {
        return new DeliveryDispatcher(new class($result) implements WhatsAppSender
        {
            public function __construct(private WhatsAppResult $result) {}

            public function sendTemplate(string $to, string $template, array $variables): WhatsAppResult
            {
                return $this->result;
            }

            public function mode(): string
            {
                return 'stand-in';
            }
        });
    }

    // ── The ledger row ───────────────────────────────────────────────────────

    public function test_a_successful_send_records_the_provider_message_id(): void
    {
        $user = $this->member();

        $delivery = $this->dispatcherReturning(WhatsAppResult::sent('wamid.ABC'))
            ->send($user, 'someone_interested', ['first_name' => 'Aisha']);

        $this->assertSame(MessageDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame('wamid.ABC', $delivery->provider_message_id);
        $this->assertNotNull($delivery->sent_at);
    }

    /**
     * ⚠️ THE ORDERED VARIABLES ARE STORED, AND THIS IS WHY.
     *
     * WhatsApp variables are positional. A wrong order sends successfully,
     * reports "delivered", and the only evidence of the corruption is the
     * sentence a member read. Storing what was actually sent is the only way
     * to prove it after the fact.
     */
    public function test_the_ledger_stores_the_variables_in_the_order_they_were_sent(): void
    {
        $user = $this->member();

        $delivery = $this->dispatcherReturning(WhatsAppResult::sent('wamid.ABC'))->send(
            $user,
            'mutual_interest',
            // Deliberately supplied the wrong way round; the declaration wins.
            ['other_first_name' => 'Omar', 'first_name' => 'Aisha'],
        );

        $this->assertSame(['Aisha', 'Omar'], $delivery->variables);
    }

    public function test_a_permanent_failure_is_recorded_as_not_retryable(): void
    {
        $user = $this->member();

        $delivery = $this->dispatcherReturning(WhatsAppResult::permanentFailure('Not a WhatsApp user', 131026))
            ->send($user, 'someone_interested', ['first_name' => 'Aisha']);

        $this->assertSame(MessageDelivery::STATUS_FAILED, $delivery->status);
        $this->assertFalse($delivery->retryable);
        $this->assertSame('131026', $delivery->error_code);
    }

    public function test_a_transient_failure_is_recorded_as_retryable(): void
    {
        $user = $this->member();

        $delivery = $this->dispatcherReturning(WhatsAppResult::temporaryFailure('Rate limited', 130429))
            ->send($user, 'someone_interested', ['first_name' => 'Aisha']);

        $this->assertTrue($delivery->retryable);
    }

    /** A member with no number fails permanently, and is never retried. */
    public function test_a_member_with_no_number_fails_without_calling_the_provider(): void
    {
        $user = User::factory()->create(['phone_country_code' => null, 'phone_number' => null]);

        $delivery = $this->dispatcherReturning(WhatsAppResult::sent('should-not-happen'))
            ->send($user, 'someone_interested', ['first_name' => 'Aisha']);

        $this->assertSame(MessageDelivery::STATUS_FAILED, $delivery->status);
        $this->assertFalse($delivery->retryable);
        $this->assertNull($delivery->provider_message_id);
    }

    // ── Status callbacks ─────────────────────────────────────────────────────

    /**
     * ⚠️ THE LADDER ONLY GOES UP.
     *
     * Meta's callbacks are not ordered — a `delivered` can arrive after a
     * `read` when a phone reconnects and flushes a queue. Applying whatever
     * came last would show a message the member has already opened going
     * backwards in the admin panel.
     */
    public function test_a_late_delivered_callback_cannot_demote_a_read_message(): void
    {
        $delivery = MessageDelivery::create([
            'user_id' => $this->member()->id,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => 'someone_interested',
            'status' => MessageDelivery::STATUS_SENT,
            'provider_message_id' => 'wamid.LADDER',
        ]);

        $this->assertTrue($delivery->applyStatus(MessageDelivery::STATUS_READ));
        $this->assertFalse($delivery->applyStatus(MessageDelivery::STATUS_DELIVERED));

        $this->assertSame(MessageDelivery::STATUS_READ, $delivery->fresh()->status);
    }

    /** ⚠️ POSITIVE CONTROL (Rule 5). A ladder that refuses everything also passes above. */
    public function test_the_ladder_does_advance_for_a_genuine_step(): void
    {
        $delivery = MessageDelivery::create([
            'user_id' => $this->member()->id,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => 'someone_interested',
            'status' => MessageDelivery::STATUS_SENT,
            'provider_message_id' => 'wamid.FORWARD',
        ]);

        $this->assertTrue($delivery->applyStatus(MessageDelivery::STATUS_DELIVERED));
        $this->assertSame(MessageDelivery::STATUS_DELIVERED, $delivery->fresh()->status);

        $this->assertTrue($delivery->applyStatus(MessageDelivery::STATUS_READ));
        $this->assertSame(MessageDelivery::STATUS_READ, $delivery->fresh()->status);
    }

    public function test_a_failed_status_from_meta_triggers_the_email_fallback(): void
    {
        Bus::fake();

        $delivery = MessageDelivery::create([
            'user_id' => $this->member()->id,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => 'introduction_delivered',
            'status' => MessageDelivery::STATUS_SENT,
            'provider_message_id' => 'wamid.FAILS',
        ]);

        (new ProcessWhatsAppWebhook($this->statusPayload('wamid.FAILS', 'failed')))->handle();

        $this->assertSame(MessageDelivery::STATUS_FAILED, $delivery->fresh()->status);
        Bus::assertDispatchedTimes(FallBackToEmail::class, 1);
    }

    /** ⚠️ A repeated callback must not produce a second email. */
    public function test_a_repeated_failure_callback_does_not_fall_back_twice(): void
    {
        Bus::fake();

        MessageDelivery::create([
            'user_id' => $this->member()->id,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => 'introduction_delivered',
            'status' => MessageDelivery::STATUS_SENT,
            'provider_message_id' => 'wamid.TWICE',
        ]);

        (new ProcessWhatsAppWebhook($this->statusPayload('wamid.TWICE', 'failed')))->handle();
        (new ProcessWhatsAppWebhook($this->statusPayload('wamid.TWICE', 'failed')))->handle();

        Bus::assertDispatchedTimes(FallBackToEmail::class, 1);
    }

    public function test_the_fallback_creates_an_email_row_and_marks_the_whatsapp_row_answered(): void
    {
        $delivery = MessageDelivery::create([
            'user_id' => $this->member()->id,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => 'introduction_delivered',
            'variables' => ['Aisha', '1'],
            'status' => MessageDelivery::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        (new FallBackToEmail($delivery->id))->handle();

        $this->assertNotNull($delivery->fresh()->fell_back_at);

        $email = MessageDelivery::where('channel', MessageDelivery::CHANNEL_EMAIL)->first();

        $this->assertNotNull($email, 'No email row was created, so the member hears nothing at all.');
        $this->assertSame(['Aisha', '1'], $email->variables);

        // And running it again does nothing — needsFallback() is now false.
        (new FallBackToEmail($delivery->id))->handle();
        $this->assertSame(1, MessageDelivery::where('channel', MessageDelivery::CHANNEL_EMAIL)->count());
    }

    // ── Opt-out (A5) ─────────────────────────────────────────────────────────

    /**
     * ⚠️ STOP IS PER-CHANNEL. Opting out of WhatsApp must not switch off
     * email — a member who says "stop messaging my phone" has not said "stop
     * telling me about the introductions I paid for".
     */
    public function test_stop_opts_out_of_whatsapp_only(): void
    {
        $user = $this->member('8765550199');

        NotificationPreference::create([
            'user_id' => $user->id,
            'whatsapp_matches' => true,
            'email_matches' => true,
        ]);

        (new ProcessWhatsAppWebhook($this->inboundPayload('18765550199', 'STOP')))->handle();

        $preference = NotificationPreference::where('user_id', $user->id)->first();

        $this->assertFalse($preference->whatsapp_matches, 'STOP did not switch WhatsApp off.');
        $this->assertTrue(
            $preference->email_matches,
            'STOP switched email off too. The member has lost the introductions they paid for '
                .'while appearing to have their wishes respected.'
        );
    }

    /** UNSUBSCRIBE and CANCEL mean the same thing, and case does not matter. */
    public function test_the_other_opt_out_words_are_honoured(): void
    {
        foreach (['UNSUBSCRIBE', 'cancel', ' Stop '] as $word) {
            $user = $this->member('87655502'.random_int(10, 99));

            (new ProcessWhatsAppWebhook($this->inboundPayload(
                preg_replace('/\D+/', '', '1'.$user->phone_number),
                $word,
            )))->handle();

            $this->assertFalse(
                NotificationPreference::where('user_id', $user->id)->first()?->whatsapp_matches,
                "[{$word}] was not treated as an opt-out."
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every opt-out assertion above is "the flag
     * became false", which is also what happens if the handler switches
     * WhatsApp off for every inbound message it ever sees.
     */
    public function test_an_ordinary_message_is_not_an_opt_out(): void
    {
        $user = $this->member('8765550199');

        NotificationPreference::create([
            'user_id' => $user->id,
            'whatsapp_matches' => true,
            'email_matches' => true,
        ]);

        (new ProcessWhatsAppWebhook($this->inboundPayload('18765550199', 'Thank you, this is wonderful')))->handle();

        $this->assertTrue(
            NotificationPreference::where('user_id', $user->id)->first()->whatsapp_matches,
            'An ordinary reply switched WhatsApp off, so every member who ever answers is silenced.'
        );
    }

    /**
     * ⚠️ THE NUMBER IS MATCHED ON DIGITS.
     *
     * We store "+1 8765550199"; Meta sends "18765550199". A raw string
     * comparison never matches, so every opt-out would be silently dropped and
     * the member would keep receiving messages after asking us to stop — which
     * is how a WhatsApp number gets banned.
     */
    public function test_the_stored_and_incoming_number_formats_are_reconciled(): void
    {
        $user = $this->member('8765550199');

        $this->assertNotSame(
            '18765550199',
            $user->fullPhone(),
            'The formats now agree, so this control no longer proves the reconciliation is needed.'
        );

        (new ProcessWhatsAppWebhook($this->inboundPayload('18765550199', 'STOP')))->handle();

        $this->assertFalse(NotificationPreference::where('user_id', $user->id)->first()?->whatsapp_matches);
    }

    // ── The 24-hour window (A6) ──────────────────────────────────────────────

    /**
     * ⚠️ THE WINDOW IS SHUT ALMOST ALL THE TIME, AND THAT IS NORMAL.
     *
     * It opens only when the MEMBER writes to us. Most never do, so for most
     * of this product's sending only an approved template is permitted — which
     * is why WhatsAppSender has no sendText() at all.
     */
    public function test_the_window_is_closed_for_a_member_who_has_never_messaged_us(): void
    {
        $window = app(\App\Services\WhatsApp\ConversationWindow::class);
        $user = $this->member();

        $this->assertFalse($window->isOpen($user));
        $this->assertFalse($window->allowsFreeForm($user, 'test'));
    }

    public function test_any_inbound_message_opens_the_window(): void
    {
        $user = $this->member('8765550199');

        $this->assertNull($user->whatsapp_last_inbound_at);

        // Deliberately an ordinary message, not an opt-out: Meta's window is
        // opened by the member writing at all, whatever they wrote.
        (new ProcessWhatsAppWebhook($this->inboundPayload('18765550199', 'Hello, thank you')))->handle();

        $this->assertTrue(app(\App\Services\WhatsApp\ConversationWindow::class)->isOpen($user->fresh()));
    }

    /** And it closes again after 24 hours. */
    public function test_the_window_closes_after_twenty_four_hours(): void
    {
        $window = app(\App\Services\WhatsApp\ConversationWindow::class);
        $user = $this->member();

        $user->forceFill(['whatsapp_last_inbound_at' => now()->subHours(23)])->save();
        $this->assertTrue($window->isOpen($user->fresh()), 'The window shut early, refusing permitted sends.');

        $user->forceFill(['whatsapp_last_inbound_at' => now()->subHours(25)])->save();
        $this->assertFalse($window->isOpen($user->fresh()), 'The window stayed open past 24 hours.');
    }

    /**
     * ⚠️ THE STRUCTURAL GUARANTEE: THERE IS NO FREE-FORM PATH TO GET WRONG.
     *
     * The window guard is a safety net. The actual defence is that the sender
     * contract has no method for sending free text, so no call site can be
     * outside the window in the first place.
     */
    public function test_the_sender_contract_offers_no_free_form_method(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass(WhatsAppSender::class))->getMethods(),
        );

        sort($methods);

        $this->assertSame(
            ['mode', 'sendTemplate'],
            $methods,
            'WhatsAppSender has gained a method. If it sends free text, every call site is now '
                .'capable of a send Meta refuses outside the 24-hour window.'
        );
    }

    /** @return array<mixed> */
    private function statusPayload(string $id, string $status): array
    {
        return ['entry' => [['changes' => [['value' => ['statuses' => [[
            'id' => $id,
            'status' => $status,
            'timestamp' => (string) now()->timestamp,
            'errors' => [['code' => 131026, 'title' => 'Not a WhatsApp user']],
        ]]]]]]]];
    }

    /** @return array<mixed> */
    private function inboundPayload(string $from, string $body): array
    {
        return ['entry' => [['changes' => [['value' => ['messages' => [[
            'from' => $from,
            'id' => 'wamid.IN'.$from,
            'type' => 'text',
            'text' => ['body' => $body],
        ]]]]]]]];
    }
}
