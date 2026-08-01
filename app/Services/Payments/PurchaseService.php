<?php

namespace App\Services\Payments;

use App\Jobs\SendReceiptEmail;
use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\Packages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The lifecycle of one purchase, from "they clicked buy" to "they have their
 * credits".
 */
class PurchaseService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CreditService $credits,
        private readonly Packages $packages,
        private readonly PaymentRedactor $redactor,
    ) {}

    /**
     * Create the row before anything is sent anywhere.
     *
     * ⚠️ Price and credit count are SNAPSHOTTED here, not looked up later.
     * If the client changes a price next month, this receipt must still show
     * what was actually paid.
     *
     * `order_identifier` is generated now because the 3DS return is a
     * cross-site POST with no session — this is how the callback finds its
     * way back to the right purchase.
     */
    public function initiate(User $user, string $packageKey): Purchase
    {
        $package = $this->packages->get($packageKey);

        return Purchase::create([
            'user_id' => $user->id,
            'package_key' => $package['key'],
            'amount_cents' => $package['price_cents'],
            'currency' => 'USD',
            'credits_granted' => $package['credits'],
            'status' => Purchase::STATUS_INITIATED,
            'gateway' => 'powertranz',
            'gateway_mode' => $this->gateway->mode(),
            'order_identifier' => (string) Str::uuid(),
        ]);
    }

    public function handOff(Purchase $purchase, string $callbackUrl): GatewayHandoff
    {
        $handoff = $this->gateway->initiate($purchase, $callbackUrl);

        $this->record($purchase, 'outbound', 'initiate', $handoff->raw);

        if (! $handoff->ok) {
            $purchase->forceFill([
                'status' => Purchase::STATUS_FAILED,
                'failure_code' => $handoff->failureCode,
                'failure_message' => $handoff->failureMessage,
                'raw_response' => $this->redactor->redact($handoff->raw),
            ])->save();

            return $handoff;
        }

        $purchase->forceFill([
            'status' => Purchase::STATUS_PENDING_3DS,
            'spi_token' => $handoff->spiToken,
        ])->save();

        return $handoff;
    }

    /**
     * Settle a purchase against the gateway's authoritative answer.
     *
     * ⚠️ ALWAYS re-queries. The inbound callback is not proof of anything —
     * there is no signed webhook here — so it is treated purely as a signal
     * meaning "go and ask now".
     *
     * Safe to call repeatedly, out of order, and for purchases that are
     * already finished.
     */
    public function settle(Purchase $purchase): Purchase
    {
        // Already done. A late or duplicate callback changes nothing.
        if ($purchase->isComplete()) {
            Log::info('Settle called on an already completed purchase; nothing to do.', [
                'purchase_id' => $purchase->id,
            ]);

            return $purchase;
        }

        if (in_array($purchase->status, [Purchase::STATUS_VOIDED, Purchase::STATUS_REFUNDED], true)) {
            return $purchase;
        }

        $status = $this->gateway->query($purchase);

        $this->record($purchase, 'inbound', 'query', $status->raw);

        if (! $status->isApproved()) {
            /*
             * A callback can claim success while the gateway reports failure.
             * The gateway wins, always, and nothing is credited.
             */
            $purchase->forceFill([
                'status' => $status->status,
                'failure_code' => $status->failureCode,
                'failure_message' => $status->failureMessage,
                'raw_response' => $this->redactor->redact($status->raw),
            ])->save();

            return $purchase->fresh();
        }

        $purchase->forceFill([
            'gateway_transaction_id' => $status->transactionId,
            'raw_response' => $this->redactor->redact($status->raw),
        ])->save();

        /*
         * One transaction, or neither. A partial failure must never leave a
         * purchase marked completed with no ledger row behind it — the grant
         * marks the purchase complete itself, inside the same transaction.
         */
        DB::transaction(function () use ($purchase) {
            $this->credits->grantForPurchase($purchase);
        });

        SendReceiptEmail::dispatch($purchase->id)->afterCommit();

        return $purchase->fresh();
    }

    public function fail(Purchase $purchase, string $code, string $message): Purchase
    {
        $purchase->forceFill([
            'status' => Purchase::STATUS_FAILED,
            'failure_code' => $code,
            'failure_message' => $message,
        ])->save();

        return $purchase->fresh();
    }

    /** Every gateway interaction, redacted, in both directions. */
    public function record(Purchase $purchase, string $direction, string $endpoint, array $payload, ?int $httpStatus = null, ?string $note = null): void
    {
        PaymentEvent::create([
            'purchase_id' => $purchase->id,
            'direction' => $direction,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'payload' => $this->redactor->redact($payload),
            'note' => $note,
        ]);
    }
}
