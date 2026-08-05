<?php

namespace App\Console\Commands;

use App\Jobs\SendReceiptEmail;
use App\Models\Purchase;
use Illuminate\Console\Command;

/**
 * Send the receipts that were never sent.
 *
 * ⚠️ SEVEN PEOPLE PAID AND RECEIVED NOTHING IN WRITING.
 *
 * The mail driver was `log` while payments were being tested, so every receipt
 * went to a file on the server. A receipt is not marketing — it is the record
 * of a transaction a member is entitled to, and it is what they reach for when
 * they do not recognise a charge on a statement.
 *
 * ⚠️ IT IS IDEMPOTENT BY THE LEDGER, NOT BY A FLAG.
 *
 * Re-running must not send anybody a second receipt for one payment. The
 * guarantee is SendReceiptEmail's own ShouldBeUnique keyed on the purchase, so
 * a duplicate dispatch collapses in the queue rather than relying on this
 * command being run carefully.
 *
 * Dry run by default. A command that emails real people on its first run is
 * one you want to have seen the output of first.
 */
class RedispatchHeldReceipts extends Command
{
    protected $signature = 'realvows:redispatch-receipts
                            {--execute : Actually queue them. Without this it is a dry run.}
                            {--since= : Only purchases completed on or after this date (Y-m-d)}';

    protected $description = 'Re-send receipts for completed purchases that never got one';

    public function handle(): int
    {
        $query = Purchase::query()
            ->where('status', 'completed')
            ->with('user')
            ->orderBy('created_at');

        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', $since);
        }

        $purchases = $query->get();

        if ($purchases->isEmpty()) {
            $this->info('No completed purchases found.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info($this->option('execute') ? '── Re-dispatching receipts ──' : '── Receipts (DRY RUN) ──');
        $this->line('');

        $queued = 0;

        foreach ($purchases as $purchase) {
            /*
             * ⚠️ A PURCHASE WITH NO USER IS REPORTED, NOT SKIPPED SILENTLY.
             *
             * It means somebody paid and their account has since gone. That is
             * a real problem — a charge with nobody to explain it to — and a
             * quiet `continue` is how it stays unnoticed.
             */
            if (! $purchase->user) {
                $this->warn(sprintf('  #%d  %s  — NO ACCOUNT. Somebody paid and the account is gone.',
                    $purchase->id, $purchase->order_identifier));

                continue;
            }

            $this->line(sprintf('  #%-4d %-22s %s', $purchase->id, $purchase->order_identifier, $purchase->user->email));

            if ($this->option('execute')) {
                SendReceiptEmail::dispatch($purchase->id);
                $queued++;
            }
        }

        $this->line('');

        if (! $this->option('execute')) {
            $this->line('  Nothing was sent. Re-run with --execute to queue them.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->info("  {$queued} receipt(s) queued.");
        $this->line('');
        $this->line('  ⚠️ Queued, not sent. The cron drains the queue within about a minute —');
        $this->line('  check Settings → Email and the delivery log to confirm they arrived.');
        $this->line('');

        return self::SUCCESS;
    }
}
