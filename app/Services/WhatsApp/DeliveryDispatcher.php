<?php

namespace App\Services\WhatsApp;

use App\Models\MessageDelivery;
use App\Models\User;
use App\Support\WhatsAppTemplates;
use Illuminate\Support\Facades\Log;

/**
 * Sends one template to one member, and writes down what happened.
 *
 * ⚠️ ONE MESSAGE PER JOB, NEVER A BATCH.
 *
 * A batch job that fails halfway has already sent some of its messages, and a
 * retry sends them again. There is no way to make that idempotent without
 * per-message bookkeeping — at which point it is per-message anyway. So the
 * unit of work is one message, and one failure can only ever cost one member.
 *
 * ⚠️ THE LEDGER ROW IS WRITTEN BEFORE THE SEND, NOT AFTER.
 *
 * If the process dies mid-call — which on shared hosting it does — a row
 * written afterwards never exists, and the only record of an attempt that may
 * have reached somebody's phone is gone. Written first, the worst case is a
 * row stuck at `queued`, which is visible and answerable. The worst case the
 * other way round is a message nobody knows was sent.
 */
class DeliveryDispatcher
{
    public function __construct(private readonly WhatsAppSender $sender) {}

    /**
     * @param  array<string, string>  $variables  Keyed by the template's declared names
     */
    public function send(User $recipient, string $template, array $variables, ?int $matchId = null): MessageDelivery
    {
        /*
         * ⚠️ ORDERED HERE, ONCE, FROM THE DECLARATION. Callers hand over a
         * keyed array and never see a position, so the order that reaches Meta
         * and the order recorded in the ledger are the same by construction.
         */
        $ordered = WhatsAppTemplates::order($template, $variables);

        $delivery = MessageDelivery::create([
            'user_id' => $recipient->id,
            'match_id' => $matchId,
            'channel' => MessageDelivery::CHANNEL_WHATSAPP,
            'template' => $template,
            'variables' => $ordered,
            'status' => MessageDelivery::STATUS_QUEUED,
            'attempts' => 1,
        ]);

        $number = preg_replace('/\D+/', '', (string) $recipient->fullPhone());

        if (! $number) {
            return $this->fail($delivery, 'No phone number on the account.', null, retryable: false);
        }

        $result = $this->sender->sendTemplate($number, $template, $ordered);

        if (! $result->sent) {
            return $this->fail($delivery, (string) $result->error, $result->code, $result->retryable);
        }

        $delivery->forceFill([
            'status' => MessageDelivery::STATUS_SENT,
            'provider_message_id' => $result->messageId,
            'sent_at' => now(),
        ])->save();

        return $delivery;
    }

    /**
     * ⚠️ A FAILURE IS RECORDED WITH ITS RETRYABILITY, NOT JUST ITS TEXT.
     *
     * Deciding later whether to retry by pattern-matching an error message
     * Meta is free to reword is how a permanent failure starts being retried
     * forever — and every attempt is billed and counts against the quality
     * rating that sets our sending limits.
     */
    private function fail(MessageDelivery $delivery, string $error, ?int $code, bool $retryable): MessageDelivery
    {
        $delivery->forceFill([
            'status' => MessageDelivery::STATUS_FAILED,
            'retryable' => $retryable,
            'error' => $error,
            'error_code' => $code === null ? null : (string) $code,
            'failed_at' => now(),
        ])->save();

        Log::channel('whatsapp-webhook')->warning('whatsapp delivery failed', [
            'delivery' => $delivery->id,
            'user' => $delivery->user_id,
            'template' => $delivery->template,
            'retryable' => $retryable,
            'code' => $code,
            'error' => $error,
        ]);

        return $delivery;
    }
}
