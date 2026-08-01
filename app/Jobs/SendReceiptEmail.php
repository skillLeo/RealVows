<?php

namespace App\Jobs;

use App\Mail\PurchaseReceipt;
use App\Models\Purchase;
use App\Services\Settings\RuntimeMailConfigurator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * The only email Phase 2 sends. Match delivery is Phase 3.
 *
 * Unique per purchase, so a retried settle cannot send two receipts for one
 * payment.
 */
class SendReceiptEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $purchaseId) {}

    public function uniqueId(): string
    {
        return (string) $this->purchaseId;
    }

    public function handle(RuntimeMailConfigurator $mail): void
    {
        $purchase = Purchase::with('user')->find($this->purchaseId);

        if (! $purchase || ! $purchase->isComplete() || ! $purchase->user) {
            return;
        }

        /*
         * A receipt for money taken is a record the member is entitled to,
         * not a marketing preference — so it is sent regardless of their
         * notification settings, which is why email_receipts has no toggle.
         */
        $mail->apply();

        Mail::to($purchase->user->email)->send(new PurchaseReceipt($purchase));
    }
}
