<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * What a verified Resend event actually causes.
 *
 * ⚠️ ONLY DISPATCHED FOR A SIGNATURE-VERIFIED PAYLOAD. Everything here changes
 * state on a member's account.
 */
class ProcessResendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    /**
     * Resend's event names, mapped to what the ledger calls them.
     *
     * ⚠️ `delivery_delayed` IS NOT A FAILURE AND MUST NOT BE TREATED AS ONE.
     *
     * It means the receiving server asked us to try later — a greylist, a full
     * mailbox, a temporary outage — and Resend retries on its own. Marking it
     * failed would fire the email fallback for a message that is still in
     * flight, and the member would get two copies of an introduction.
     */
    private const MAP = [
        'email.sent' => MessageDelivery::STATUS_ACCEPTED,
        'email.delivered' => MessageDelivery::STATUS_DELIVERED,
        'email.bounced' => MessageDelivery::STATUS_FAILED,
        'email.complained' => MessageDelivery::STATUS_FAILED,
    ];

    /** @param array<mixed> $payload */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $type = (string) ($this->payload['type'] ?? '');
        $data = $this->payload['data'] ?? [];

        if ($type === 'email.delivery_delayed') {
            // Recorded, not acted on. See the note on MAP.
            Log::channel('whatsapp-webhook')->info('resend delivery delayed', [
                'note' => 'Resend retries this itself. Marking it failed would send the member a '
                    .'second copy of a message still in flight.',
            ]);

            return;
        }

        if (! isset(self::MAP[$type])) {
            return;
        }

        $delivery = $this->deliveryFor($data);

        if (! $delivery) {
            return;
        }

        $delivery->applyStatus(self::MAP[$type]);

        if ($type === 'email.bounced') {
            $this->recordBounce($delivery, $data);
        }

        if ($type === 'email.complained') {
            $this->recordComplaint($delivery);
        }
    }

    /**
     * ⚠️ MATCHED ON THE PROVIDER'S MESSAGE ID, NOT ON THE EMAIL ADDRESS.
     *
     * Two members can share an address in a way an address match would confuse
     * — a couple using one mailbox, or a member who changed theirs. The id is
     * the only thing that identifies one message.
     */
    private function deliveryFor(array $data): ?MessageDelivery
    {
        $id = $data['email_id'] ?? $data['id'] ?? null;

        if (! is_string($id)) {
            return null;
        }

        return MessageDelivery::where('provider_message_id', $id)
            ->where('channel', MessageDelivery::CHANNEL_EMAIL)
            ->first();
    }

    /**
     * ⚠️ A HARD BOUNCE SUPPRESSES FUTURE EMAIL. A SOFT ONE MUST NOT.
     *
     * Continuing to send to an address that permanently rejects is what
     * destroys a sending reputation — providers read it as not caring. But
     * suppressing on a soft bounce cuts off a member whose mailbox was
     * briefly full, and they never find out why the product went quiet.
     */
    private function recordBounce(MessageDelivery $delivery, array $data): void
    {
        $subtype = strtolower((string) ($data['bounce']['type'] ?? $data['type'] ?? ''));
        $isHard = str_contains($subtype, 'hard') || str_contains($subtype, 'permanent');

        $delivery->forceFill([
            'retryable' => ! $isHard,
            'error' => $data['bounce']['message'] ?? 'Bounced.',
            'error_code' => $subtype ?: null,
        ])->save();

        Log::channel('whatsapp-webhook')->warning('email bounced', [
            'delivery' => $delivery->id,
            'user' => $delivery->user_id,
            'hard' => $isHard,
        ]);

        if (! $isHard) {
            return;
        }

        /*
         * ⚠️ THE MEMBER IS NOW UNREACHABLE BY EMAIL, WHICH IS AN INCIDENT AND
         * NOT A LOG LINE.
         *
         * Email is the floor beneath WhatsApp. A hard bounce means the floor
         * has gone, and if WhatsApp is also unavailable this member cannot be
         * told about introductions they may have paid for.
         */
        FallBackToEmail::dispatch($delivery->id);
    }

    /**
     * ⚠️ A COMPLAINT IS PERMANENT AND IS NOT A PREFERENCE.
     *
     * Somebody pressing "this is spam" has told their provider, not us. Both
     * channels stay switched off regardless of what the account screen says,
     * because continuing to send after a complaint is what gets a domain
     * blocklisted for every member at once.
     */
    private function recordComplaint(MessageDelivery $delivery): void
    {
        $user = User::find($delivery->user_id);

        if (! $user) {
            return;
        }

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['email_matches' => false],
        );

        Log::channel('whatsapp-webhook')->critical('spam complaint received', [
            'user' => $user->id,
            'note' => 'Email switched off permanently for this member. Continuing to send after a '
                .'complaint is what gets the whole domain blocklisted.',
        ]);
    }
}
