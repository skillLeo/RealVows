<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePendingPayments;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'realvows:reconcile-payments';

    protected $description = 'Ask the gateway about purchases whose outcome never arrived';

    public function handle(): int
    {
        ReconcilePendingPayments::dispatchSync();

        $this->info('Reconciliation complete.');

        return self::SUCCESS;
    }
}
