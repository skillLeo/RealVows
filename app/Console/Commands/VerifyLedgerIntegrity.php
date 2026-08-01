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

    public function __construct(
        private readonly \App\Services\Settings\SettingsService $settings,
        private readonly \App\Services\Settings\RuntimeMailConfigurator $mail,
    ) {
        parent::__construct();
    }

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

        /*
         * ⚠️ AN INTEGRITY CHECK NOBODY READS IS NOT A CHECK.
         *
         * This runs at 03:40 from a cron whose output goes to /dev/null. A
         * critical log line in a file nobody opens is indistinguishable from
         * silence, so a discrepancy in the record of members' money has to
         * arrive somewhere a person actually looks.
         */
        $this->alertAHuman($problems);

        return self::FAILURE;
    }

    /**
     * @param  list<array<string, mixed>>  $problems
     */
    private function alertAHuman(array $problems): void
    {
        $to = $this->settings->get('general', 'admin_notification_email')
            ?: $this->settings->get('general', 'support_email');

        if (! $to) {
            $this->warn('No admin notification email is set, so nobody has been told. Set one in /admin/settings.');

            return;
        }

        try {
            $this->mail->apply();

            \Illuminate\Support\Facades\Mail::raw(
                "The nightly credit ledger check found ".count($problems)." discrepancies on ".config('app.url').".\n\n"
                ."This means a member's recorded balance disagrees with the sum of their ledger, which should be "
                ."impossible through the application. Someone should look at this today.\n\n"
                .json_encode($problems, JSON_PRETTY_PRINT),
                fn ($m) => $m->to($to)->subject('RealVows: credit ledger discrepancy found')
            );

            $this->line("Alert sent to {$to}.");
        } catch (\Throwable $e) {
            // Never let the alert failing hide the finding itself.
            $this->error('Could not send the alert: '.$e->getMessage());
            Log::critical('Ledger alert email failed', ['error' => $e->getMessage()]);
        }
    }
}
