<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A WhatsApp message failed permanently, so the member is told by email.
 *
 * ⚠️ A MEMBER MUST NEVER SILENTLY MISS AN INTRODUCTION THEY PAID FOR.
 *
 * That is the whole reason this exists. WhatsApp is the channel we prefer, not
 * a channel we rely on — a number can be off WhatsApp, blocked, or simply
 * wrong, and every one of those failures is invisible to the member. Email is
 * the floor beneath it.
 *
 * ⚠️ IT FIRES IMMEDIATELY, NOT ON A SWEEP. A nightly job that catches failed
 * deliveries means a member hears about an introduction a day late, which on a
 * platform where introductions expire is materially worse than not preferring
 * WhatsApp at all.
 *
 * ⚠️ UNIQUE PER DELIVERY, and `fell_back_at` is the second guard. A repeated
 * status callback from Meta — which happens — must not produce a second email
 * for one failure.
 */
class FallBackToEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(): void
    {
        $delivery = MessageDelivery::with('user')->find($this->deliveryId);

        if (! $delivery || ! $delivery->user) {
            return;
        }

        /*
         * ⚠️ RE-CHECKED RATHER THAN ASSUMED. Between the dispatch and this
         * running, a later callback may have moved the row on, or another
         * fallback may already have gone out.
         */
        if (! $delivery->needsFallback()) {
            return;
        }

        /*
         * ⚠️ THE EMAIL ITSELF IS PART B AND IS NOT BUILT YET.
         *
         * This is deliberately a recorded, visible gap rather than a silent
         * one: the ledger gets an email row in `queued`, and the log says at
         * warning that a member is currently owed a message nothing has sent.
         * When the mailables land in Part B, this method sends one and the row
         * moves to `sent`.
         *
         * Written this way round because the alternative — wiring the fallback
         * later — means the failure path is the last thing built and the least
         * tested, which is exactly backwards for the path that exists to catch
         * everything else going wrong.
         */
        $email = MessageDelivery::create([
            'user_id' => $delivery->user_id,
            'match_id' => $delivery->match_id,
            'channel' => MessageDelivery::CHANNEL_EMAIL,
            'template' => $delivery->template,
            'variables' => $delivery->variables,
            'status' => MessageDelivery::STATUS_QUEUED,
        ]);

        $delivery->forceFill(['fell_back_at' => now()])->save();

        Log::channel('whatsapp-webhook')->warning('whatsapp failed, email fallback queued', [
            'whatsapp_delivery' => $delivery->id,
            'email_delivery' => $email->id,
            'user' => $delivery->user_id,
            'template' => $delivery->template,
            'pending' => 'Part B mailables are not built yet, so this row stays queued.',
        ]);
    }
}
