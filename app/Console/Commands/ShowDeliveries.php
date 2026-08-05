<?php

namespace App\Console\Commands;

use App\Models\MessageDelivery;
use Illuminate\Console\Command;

/**
 * The delivery ledger, readable without SSH.
 *
 * ⚠️ "DID IT ARRIVE?" IS THE ONLY QUESTION THIS LEDGER EXISTS TO ANSWER, AND
 * IT WAS ONLY ANSWERABLE FROM A DATABASE CLIENT.
 *
 * The admin panel will render this eventually. Until then the counts have to
 * be reachable, because the alternative during a launch is somebody deciding
 * whether seven receipts went out by looking at their own inbox.
 *
 * ⚠️ IT PRINTS THE STATUS LABELS, NOT THE RAW COLUMN. `accepted` and
 * `delivered` mean genuinely different things and the difference is the whole
 * point of the ceiling work — an operator reading "accepted" as "delivered" is
 * the misunderstanding the labels exist to prevent.
 */
class ShowDeliveries extends Command
{
    protected $signature = 'realvows:deliveries
                            {--template= : Only this template, e.g. receipt}
                            {--limit=30 : How many rows}';

    protected $description = 'Show the message delivery ledger';

    public function handle(): int
    {
        $query = MessageDelivery::query()->with('user')->latest('id');

        if ($template = $this->option('template')) {
            $query->where('template', $template);
        }

        $rows = $query->limit((int) $this->option('limit'))->get();

        if ($rows->isEmpty()) {
            $this->warn('No deliveries recorded.');

            return self::SUCCESS;
        }

        $this->line('');

        foreach ($rows as $row) {
            $this->line(sprintf(
                '  #%-4d %-9s %-22s %-28s %s',
                $row->id,
                $row->channel,
                $row->template,
                $row->label(),
                $row->user?->email ?? '(no account)',
            ));

            if ($row->error) {
                $this->line('        ↳ '.$row->error);
            }
        }

        $this->line('');

        /*
         * ⚠️ THE SUMMARY IS BY LABEL, NOT BY RAW STATUS. An operator counting
         * "sent" would include accepted rows, which is precisely the conflation
         * the labels exist to stop.
         */
        $summary = $rows->groupBy(fn (MessageDelivery $d) => $d->label())
            ->map->count()
            ->sortDesc();

        $this->info('── Summary ──');

        foreach ($summary as $label => $count) {
            $this->line(sprintf('  %-30s %d', $label, $count));
        }

        $unconfirmed = $rows->where('channel', MessageDelivery::CHANNEL_EMAIL)
            ->where('status', MessageDelivery::STATUS_ACCEPTED)
            ->count();

        if ($unconfirmed > 0) {
            $this->line('');
            $this->warn("  {$unconfirmed} email(s) handed over but not yet confirmed delivered.");
            $this->line('  Resend usually confirms within seconds. Still here after an hour means');
            $this->line('  either the webhook is not reaching us or the provider id was never');
            $this->line('  recorded — check: php artisan realvows:webhook-log --filter=resend');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
