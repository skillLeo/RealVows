<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Services\Payments\PurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Finds purchases the gateway never told us the outcome of.
 *
 * ⚠️ This is what saves a member who paid and never got their credits
 * because a callback was lost in transit. Without it, the only way that
 * surfaces is a complaint — and by then they have decided the platform
 * cannot be trusted with money.
 *
 * Deliberately conservative about how recent is too recent: a purchase that
 * is two minutes old may simply have somebody sitting on their bank's 3DS
 * screen, and re-querying underneath them would be wrong.
 */
class ReconcilePendingPayments implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Old enough that a member is no longer mid-challenge. */
    private const MIN_AGE_MINUTES = 15;

    /** Past this, a gateway that still says nothing is never going to. */
    private const GIVE_UP_AFTER_HOURS = 72;

    public function handle(PurchaseService $purchases): void
    {
        $reconciled = 0;
        $abandoned = 0;

        Purchase::query()
            ->whereIn('status', [Purchase::STATUS_PENDING_3DS, Purchase::STATUS_AUTHORISED])
            ->where('created_at', '<', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->orderBy('id')
            ->chunkById(50, function ($batch) use ($purchases, &$reconciled, &$abandoned) {
                foreach ($batch as $purchase) {
                    if ($purchase->created_at->lt(now()->subHours(self::GIVE_UP_AFTER_HOURS))) {
                        $purchases->fail($purchase, 'timeout', 'No response from the payment gateway.');
                        $abandoned++;

                        continue;
                    }

                    try {
                        $purchases->settle($purchase);
                        $reconciled++;
                    } catch (\Throwable $e) {
                        // One bad purchase must not stop the sweep finding
                        // the others.
                        Log::error('Reconciliation failed for a purchase', [
                            'purchase_id' => $purchase->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        if ($reconciled || $abandoned) {
            Log::info('Payment reconciliation swept', compact('reconciled', 'abandoned'));
        }
    }
}
