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

    public function handle(RuntimeMailConfigurator $mail, \App\Services\Mail\ResendMailer $mailer): void
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

        /*
         * ⚠️ THROUGH THE LEDGER, NOT STRAIGHT TO Mail::send.
         *
         * A receipt sent directly leaves no row, so Resend's delivery webhook
         * has nothing to attach a `delivered` event to and the message is
         * invisible in the delivery log forever. For a receipt that matters
         * more than for anything else: it is the document a member reaches for
         * when they do not recognise a charge, and "did it arrive?" has to be
         * answerable months later.
         */
        $mailer->send($purchase->user, new PurchaseReceipt($purchase), 'receipt');
    }
}
