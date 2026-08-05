<?php

namespace Tests\Feature;

use App\Jobs\ProcessResendWebhook;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * ⚠️ SVIX, NOT THE HMAC SCHEME META USES — AND EVERY DIFFERENCE IS A WAY TO
 * WRITE A VERIFIER THAT SILENTLY REJECTS EVERYTHING.
 *
 * Svix signs `{id}.{timestamp}.{body}` rather than the body alone,
 * base64-encodes rather than hex, sends a space-separated list of versioned
 * signatures, and hides the real key behind a `whsec_` prefix that has to be
 * stripped and base64-decoded.
 *
 * Get any of those wrong and the endpoint refuses every genuine event — which
 * looks exactly like a provider that is not sending any. So this file signs
 * payloads the way Svix does and proves the verifier accepts them, then
 * tampers with each part in turn.
 */
class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/webhook/resend';

    /** A real-shaped secret: `whsec_` + base64 key material. */
    private const SECRET = 'whsec_'.'c2VjcmV0LWtleS1tYXRlcmlhbC1mb3ItdGVzdHM=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function configure(): void
    {
        app(SettingsService::class)->set('mail', 'webhook_secret', self::SECRET);
    }

    /** Signed exactly the way Svix signs. */
    private function send(array $payload, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $id = $overrides['id'] ?? 'msg_2abc';
        $timestamp = $overrides['timestamp'] ?? (string) time();

        $key = base64_decode(substr(self::SECRET, 6), true);
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        return $this->call('POST', self::URL, [], [], [], array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => $overrides['signature'] ?? "v1,{$signature}",
        ]), $body);
    }

    private function event(string $type, string $emailId, array $data = []): array
    {
        return ['type' => $type, 'data' => array_merge(['email_id' => $emailId], $data)];
    }

    private function delivery(string $providerId, string $status = MessageDelivery::STATUS_ACCEPTED): MessageDelivery
    {
        return MessageDelivery::create([
            'user_id' => User::factory()->create()->id,
            'channel' => MessageDelivery::CHANNEL_EMAIL,
            'template' => 'introduction_delivered',
            'status' => $status,
            'provider_message_id' => $providerId,
        ]);
    }

    // ── Signature verification ───────────────────────────────────────────────

    /** ⚠️ POSITIVE CONTROL: a correctly signed event is ACCEPTED. */
    public function test_a_correctly_signed_event_is_accepted_and_queued(): void
    {
        Bus::fake();
        $this->configure();

        $this->send($this->event('email.delivered', 'em_1'))->assertOk();

        Bus::assertDispatchedTimes(ProcessResendWebhook::class, 1);
    }

    /**
     * ⚠️ THE CONTROL THAT MATTERS MOST HERE. Without it, every rejection test
     * below passes against a verifier that rejects everything — which is the
     * commonest way a Svix implementation is wrong.
     */
    public function test_the_verifier_is_not_simply_rejecting_everything(): void
    {
        $this->configure();

        $this->send($this->event('email.delivered', 'em_ok'))->assertOk();
        $this->send($this->event('email.bounced', 'em_ok2'))->assertOk();
    }

    public function test_a_tampered_body_is_refused(): void
    {
        $this->configure();

        $body = json_encode($this->event('email.delivered', 'em_1'));
        $id = 'msg_x';
        $timestamp = (string) time();
        $key = base64_decode(substr(self::SECRET, 6), true);
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        // Same signature, different body.
        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
        ], json_encode($this->event('email.bounced', 'em_1')))->assertForbidden();
    }

    public function test_a_forged_signature_is_refused(): void
    {
        $this->configure();

        $this->send($this->event('email.delivered', 'em_1'), ['signature' => 'v1,ZmFrZQ=='])
            ->assertForbidden();
    }

    /**
     * ⚠️ REPLAY PROTECTION. A signature alone is replayable forever, and since
     * a bounce suppresses future email, a replayable bounce is a way to cut a
     * member off from the product silently.
     */
    public function test_a_stale_event_is_refused_even_though_it_is_correctly_signed(): void
    {
        $this->configure();

        $this->send(
            $this->event('email.delivered', 'em_1'),
            ['timestamp' => (string) (time() - 3600)],
        )->assertForbidden();
    }

    /**
     * ⚠️ THE HEADER CARRIES A LIST. Svix sends every currently-valid version
     * during a rotation, so a verifier comparing the whole header string breaks
     * on the day somebody rotates a secret.
     */
    public function test_a_signature_list_is_accepted_when_one_entry_matches(): void
    {
        Bus::fake();
        $this->configure();

        $body = json_encode($this->event('email.delivered', 'em_1'));
        $id = 'msg_rot';
        $timestamp = (string) time();
        $key = base64_decode(substr(self::SECRET, 6), true);
        $good = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            // An old secret's signature first, ours second.
            'HTTP_SVIX_SIGNATURE' => "v1,b3RoZXI= v1,{$good}",
        ], $body)->assertOk();

        Bus::assertDispatchedTimes(ProcessResendWebhook::class, 1);
    }

    /** ⚠️ Unverifiable is inert, not rejected — same shape as the WhatsApp hook. */
    public function test_an_unverifiable_event_is_acknowledged_but_queues_nothing(): void
    {
        Bus::fake();
        // Deliberately no secret configured.

        $this->send($this->event('email.delivered', 'em_1'))->assertOk();

        Bus::assertNothingDispatched();
    }

    // ── What the events do ───────────────────────────────────────────────────

    public function test_a_delivered_event_lifts_the_row_to_delivered(): void
    {
        $delivery = $this->delivery('em_ok');

        (new ProcessResendWebhook($this->event('email.delivered', 'em_ok')))->handle();

        $this->assertSame(MessageDelivery::STATUS_DELIVERED, $delivery->fresh()->status);
    }

    /**
     * ⚠️ A HARD BOUNCE IS PERMANENT AND FIRES THE FALLBACK. Continuing to send
     * to an address that permanently rejects is what destroys a sending
     * reputation.
     */
    public function test_a_hard_bounce_is_recorded_as_permanent(): void
    {
        Bus::fake();
        $delivery = $this->delivery('em_bounce');

        (new ProcessResendWebhook($this->event('email.bounced', 'em_bounce', [
            'bounce' => ['type' => 'Permanent', 'message' => 'No such recipient'],
        ])))->handle();

        $fresh = $delivery->fresh();

        $this->assertSame(MessageDelivery::STATUS_FAILED, $fresh->status);
        $this->assertFalse($fresh->retryable, 'A hard bounce was marked retryable.');
    }

    /**
     * ⚠️ A SOFT BOUNCE MUST NOT SUPPRESS. Cutting off a member whose mailbox
     * was briefly full means they never find out why the product went quiet.
     */
    public function test_a_soft_bounce_stays_retryable(): void
    {
        Bus::fake();
        $delivery = $this->delivery('em_soft');

        (new ProcessResendWebhook($this->event('email.bounced', 'em_soft', [
            'bounce' => ['type' => 'Transient', 'message' => 'Mailbox full'],
        ])))->handle();

        $this->assertTrue(
            $delivery->fresh()->retryable,
            'A temporarily full mailbox was treated as permanent, so this member is now cut off.'
        );
    }

    /**
     * ⚠️ `delivery_delayed` IS NOT A FAILURE.
     *
     * Resend retries it itself. Marking it failed would fire the email
     * fallback for a message still in flight and send the member two copies.
     */
    public function test_a_delayed_delivery_is_not_treated_as_a_failure(): void
    {
        $delivery = $this->delivery('em_slow');

        (new ProcessResendWebhook($this->event('email.delivery_delayed', 'em_slow')))->handle();

        $this->assertSame(
            MessageDelivery::STATUS_ACCEPTED,
            $delivery->fresh()->status,
            'A delayed delivery was marked failed, so the member will receive two copies of an '
                .'introduction still in flight.'
        );
    }

    /** ⚠️ A complaint is permanent and is not a preference. */
    public function test_a_complaint_switches_email_off_permanently(): void
    {
        $delivery = $this->delivery('em_spam');

        NotificationPreference::create([
            'user_id' => $delivery->user_id,
            'email_matches' => true,
            'whatsapp_matches' => true,
        ]);

        (new ProcessResendWebhook($this->event('email.complained', 'em_spam')))->handle();

        $preference = NotificationPreference::where('user_id', $delivery->user_id)->first();

        $this->assertFalse($preference->email_matches);
        $this->assertTrue(
            $preference->whatsapp_matches,
            'A spam complaint about email also switched off WhatsApp. They are separate channels '
                .'and the member complained about one of them.'
        );
    }

    /** An event for a message we never sent is ignored rather than exploding. */
    public function test_an_unknown_message_id_is_ignored(): void
    {
        (new ProcessResendWebhook($this->event('email.delivered', 'em_never_seen')))->handle();

        $this->assertTrue(true);
    }
}
