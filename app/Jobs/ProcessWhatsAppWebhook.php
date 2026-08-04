<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Everything a verified webhook payload actually causes.
 *
 * ⚠️ QUEUED, BECAUSE META MEASURES THE RESPONSE AND NOT THE WORK.
 *
 * The endpoint has a few hundred milliseconds to answer 200. Meta retries
 * anything slower and disables a subscription that keeps timing out, so the
 * controller acknowledges and this runs afterwards — which on shared hosting
 * with a one-minute cron is up to a minute later. That is the honest promise
 * and it is why nothing here is described as instant.
 *
 * ⚠️ IT IS ONLY EVER DISPATCHED FOR A SIGNATURE-VERIFIED PAYLOAD. Everything
 * below changes state: a status marks a delivery failed and triggers an email
 * fallback, an inbound STOP opts a member out of a channel. Reachable by an
 * anonymous POST, those are somebody else's controls over a member's account.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    /** @param array<mixed> $payload */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        foreach ($this->changes() as $value) {
            foreach ($value['statuses'] ?? [] as $status) {
                $this->applyStatus($status);
            }

            foreach ($value['messages'] ?? [] as $message) {
                $this->handleInbound($message);
            }
        }
    }

    /**
     * Meta nests everything three levels deep and every level is a list.
     *
     * @return list<array<mixed>>
     */
    private function changes(): array
    {
        $out = [];

        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (is_array($change['value'] ?? null)) {
                    $out[] = $change['value'];
                }
            }
        }

        return $out;
    }

    /**
     * sent → delivered → read → failed, found by the provider's message id.
     *
     * ⚠️ THE LADDER ONLY GOES UP. Meta's callbacks are not ordered: a
     * `delivered` can arrive after a `read` when a phone reconnects and
     * flushes its queue. Applying whatever came last would show a message the
     * member has already opened going backwards.
     */
    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (! is_string($id) || ! is_string($state)) {
            return;
        }

        $delivery = MessageDelivery::where('provider_message_id', $id)->first();

        if (! $delivery) {
            // Common and harmless: a status for a message sent before this
            // ledger existed, or from another environment sharing the number.
            return;
        }

        $at = isset($status['timestamp']) ? now()->setTimestamp((int) $status['timestamp']) : null;

        if (! $delivery->applyStatus($state, $at)) {
            return;
        }

        if ($state === MessageDelivery::STATUS_FAILED) {
            /*
             * ⚠️ META SAID IT FAILED, WHICH IS DIFFERENT FROM OUR SEND CALL
             * FAILING. The API accepted the message and could not deliver it —
             * the member's number is off WhatsApp, or they blocked the
             * business. Never worth retrying, and the reason email exists.
             */
            $delivery->forceFill([
                'retryable' => false,
                'error' => $status['errors'][0]['title'] ?? 'Reported failed by Meta.',
                'error_code' => isset($status['errors'][0]['code']) ? (string) $status['errors'][0]['code'] : null,
            ])->save();

            FallBackToEmail::dispatch($delivery->id);
        }
    }

    /**
     * ⚠️ STOP MEANS STOP, AND IT MEANS IT ON THIS CHANNEL ONLY.
     *
     * Opting out of WhatsApp must not silently disable email. A member who
     * says "stop messaging my phone" has not said "stop telling me about
     * introductions I paid for" — collapsing the two loses them the thing they
     * bought while appearing to respect their wishes.
     */
    private function handleInbound(array $message): void
    {
        $from = $message['from'] ?? null;

        if (! is_string($from)) {
            return;
        }

        $user = $this->userForNumber($from);

        /*
         * ⚠️ ANY INBOUND MESSAGE OPENS THE 24-HOUR WINDOW, NOT JUST AN
         * INTERESTING ONE.
         *
         * Meta's window is opened by the member writing to us at all — what
         * they wrote is irrelevant. Recording it only for messages we act on
         * would leave the window looking shut while Meta considered it open,
         * so we would refuse sends that were in fact permitted.
         */
        if ($user) {
            app(\App\Services\WhatsApp\ConversationWindow::class)->open($user);
        }

        $text = strtoupper(trim((string) ($message['text']['body'] ?? '')));

        if (! in_array($text, ['STOP', 'UNSUBSCRIBE', 'CANCEL'], true)) {
            return;
        }

        if (! $user) {
            Log::channel('whatsapp-webhook')->warning('opt-out from an unrecognised number', [
                'reason' => 'No account matches the sender, so the opt-out could not be applied.',
            ]);

            return;
        }

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['whatsapp_matches' => false],
        );

        Log::channel('whatsapp-webhook')->info('member opted out of whatsapp', [
            'user' => $user->id,
            // Email deliberately untouched — see the note above.
            'email_still_enabled' => true,
        ]);
    }

    /**
     * ⚠️ MATCHED ON DIGITS ONLY.
     *
     * We store "+1 8765550199"; Meta sends "18765550199". Comparing the raw
     * strings never matches, so every opt-out would be silently dropped and
     * the member would keep receiving messages after asking us to stop —
     * which is the kind of failure that gets a WhatsApp number banned.
     */
    private function userForNumber(string $from): ?User
    {
        $digits = preg_replace('/\D+/', '', $from);

        return User::query()
            ->whereNotNull('phone_number')
            ->get()
            ->first(fn (User $user) => preg_replace('/\D+/', '', (string) $user->fullPhone()) === $digits);
    }
}
