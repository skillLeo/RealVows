<?php

namespace App\Console\Commands;

use App\Models\CreditLedgerEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Walks every member's credit chain and proves nobody's balance drifted.
 *
 * `balance_after` is written inside the same locked transaction as its own
 * insert, and creditBalance() sums `delta` independently. If those two ever
 * disagree, either a row was altered outside the application or a write
 * escaped its transaction — both are serious, and both are silent without
 * something like this looking for them.
 *
 * Runs nightly. Reports rather than repairs: an automatic fix to a money
 * record would destroy the evidence of whatever caused the problem.
 */
class VerifyLedgerIntegrity extends Command
{
    protected $signature = 'realvows:verify-ledger';

    protected $description = 'Check every credit ledger chain against its running balance';

    public function handle(): int
    {
        $problems = [];

        User::query()
            ->whereHas('creditEntries')
            ->chunkById(100, function ($users) use (&$problems) {
                foreach ($users as $user) {
                    $running = 0;

                    $entries = CreditLedgerEntry::query()
                        ->where('user_id', $user->id)
                        ->orderBy('id')
                        ->get(['id', 'delta', 'balance_after']);

                    foreach ($entries as $entry) {
                        $running += $entry->delta;

                        if ($running !== $entry->balance_after) {
                            $problems[] = [
                                'user_id' => $user->id,
                                'ledger_id' => $entry->id,
                                'expected' => $running,
                                'stored' => $entry->balance_after,
                            ];
                        }
                    }

                    if ($running < 0) {
                        $problems[] = [
                            'user_id' => $user->id,
                            'ledger_id' => null,
                            'expected' => 'a balance of zero or more',
                            'stored' => $running,
                        ];
                    }
                }
            });

        if ($problems === []) {
            $this->info('Ledger intact. Every chain matches its running balance.');

            return self::SUCCESS;
        }

        $this->error(count($problems).' ledger discrepancies found.');
        $this->table(['User', 'Ledger row', 'Expected', 'Stored'], array_map('array_values', $problems));

        Log::critical('Credit ledger integrity check failed', ['problems' => $problems]);

        return self::FAILURE;
    }
}
