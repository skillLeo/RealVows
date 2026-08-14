<?php

namespace App\Console\Commands;

use App\Mail\OperationalAlert;
use App\Models\MessageDelivery;
use App\Services\Settings\RuntimeMailConfigurator;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ THE THINGS THAT FAIL WITHOUT ANYBODY FINDING OUT.
 *
 * ══════════════════════════════════════════════════════════════════════════
 * This exists because of the eleventh fault, which was not a check that could
 * not see. It was a check that saw, told the truth, and was heard by nobody.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * Three conditions on this platform were being detected correctly and consumed
 * by no one:
 *
 *  · `failed_jobs`. A job that fails three times lands there and stops. On this
 *    host that includes outbound email and WhatsApp, so the failure mode is a
 *    MEMBER WHO NEVER HEARS ABOUT AN INTRODUCTION while the platform believes
 *    it told them. That is the core product failing silently, and nothing read
 *    the table.
 *
 *  · Error-level log lines. Thirty-two sat unread on production. Reading them
 *    by hand is what found twenty-two missing guardian photographs and a seeder
 *    that had died under --no-dev. No error tracking is installed.
 *
 *  · Deliveries recorded as failed. Visible only to somebody who opens the
 *    admin screen and thinks to look.
 *
 * ── ⚠️ IT IS SILENT WHEN THERE IS NOTHING TO SAY ──────────────────────────
 *
 * No daily "all is well" message. A mail that arrives every morning and means
 * nothing trains the person receiving it to archive it unread, and the one that
 * matters arrives into that same habit. Mail is sent ONLY when a human needs to
 * act, so its arrival is itself the signal.
 *
 * ── ⚠️ IT FAILS NOISY, WHICH IS THE OPPOSITE OF THE PHOTOGRAPH RULE ───────
 *
 * If the marker recording what has already been reported is lost, this reports
 * everything again rather than nothing. A duplicate alert is an annoyance a
 * person recovers from in a second. A missed one is the entire failure this
 * command was written to end, and the direction of that trade is deliberate.
 */
class ReportSilentFailures extends Command
{
    protected $signature = 'realvows:report-silent-failures
                            {--dry-run : Print the report and send no mail}
                            {--force : Report everything, ignoring what was already sent}
                            {--log= : Read this log file instead of the default}';

    protected $description = 'Find the failures nobody is watching, and tell somebody.';

    /** Where the last reported point is remembered. */
    private const MARKER = 'app/last-failure-report.txt';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly RuntimeMailConfigurator $mail,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $since = $this->option('force') ? null : $this->lastReportedAt();

        $findings = array_filter([
            'Failed background jobs' => $this->failedJobs(),
            'Errors in the log' => $this->logErrors($since),
            'Messages that did not reach a member' => $this->failedDeliveries($since),
        ]);

        if ($findings === []) {
            $this->info('Nothing needs a human. No mail sent.');
            $this->rememberNow();

            return self::SUCCESS;
        }

        foreach ($findings as $heading => $lines) {
            $this->newLine();
            $this->error($heading);

            foreach ($lines as $line) {
                $this->line('  '.$line);
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Dry run. Nothing was sent and the marker was not moved.');

            return self::FAILURE;
        }

        $this->send($findings);
        $this->rememberNow();

        /*
         * ⚠️ NON-ZERO SO THE SCHEDULER LOG SHOWS IT WITHOUT ANYBODY READING
         * THE BODY. A finding is a failure of the platform, not of this command,
         * and the exit code is the cheapest signal there is.
         */
        return self::FAILURE;
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function failedJobs(): array
    {
        if (! $this->tableExists('failed_jobs')) {
            return [];
        }

        $rows = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(10)->get();
        $total = DB::table('failed_jobs')->count();

        if ($total === 0) {
            return [];
        }

        $lines = [
            "{$total} job".($total === 1 ? '' : 's').' failed and stopped retrying.',
            'Anything queued here never happened. On this host that includes email and WhatsApp,',
            'so a member may be waiting for an introduction they were never actually sent.',
            '',
        ];

        foreach ($rows as $row) {
            // The class name is the useful part. The full payload is a wall of
            // serialised PHP and belongs in the table, not in an alert.
            $payload = json_decode($row->payload ?? '{}', true);
            $name = $payload['displayName'] ?? 'unknown job';
            $first = trim(strtok((string) $row->exception, "\n") ?: 'no message');

            $lines[] = "{$row->failed_at}  {$name}  ::  ".mb_substr($first, 0, 160);
        }

        if ($total > count($rows)) {
            $lines[] = '… and '.($total - count($rows)).' more.';
        }

        return $lines;
    }

    /** @return list<string> */
    private function logErrors(?string $since): array
    {
        /*
         * ⚠️ OVERRIDABLE SO A TEST IS NOT READING THE DEVELOPER'S OWN LOG.
         *
         * The first version always read storage/logs/laravel.log, so the case
         * asserting "nothing to report" failed on a machine whose log held
         * 1,721 lines of ordinary test noise. A guard that passes or fails on
         * local state is the fault this project has met eleven times.
         */
        $path = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));

        if (! is_file($path)) {
            return [];
        }

        $lines = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            if (! preg_match('/^\[([\d-]+ [\d:]+)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $line, $m)) {
                continue;
            }

            // Only what has happened since the last report, so the same fault
            // is not re-sent every day until somebody clears the log.
            if ($since !== null && $m[1] <= $since) {
                continue;
            }

            $lines[] = "{$m[1]}  {$m[2]}  ".mb_substr(trim($m[3]), 0, 160);
        }

        fclose($handle);

        if ($lines === []) {
            return [];
        }

        $shown = array_slice($lines, -15);
        $count = count($lines);

        return array_merge(
            [$count.' error-level line'.($count === 1 ? '' : 's').' since the last report.', ''],
            $shown,
        );
    }

    /** @return list<string> */
    private function failedDeliveries(?string $since): array
    {
        if (! $this->tableExists('message_deliveries')) {
            return [];
        }

        $query = MessageDelivery::query()->where('status', 'failed');

        if ($since !== null) {
            $query->where('created_at', '>', $since);
        }

        $rows = $query->latest()->limit(10)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $lines = [$rows->count().' message(s) recorded as not delivered.', ''];

        foreach ($rows as $row) {
            $lines[] = "{$row->created_at}  {$row->channel}  {$row->template}  ::  "
                .mb_substr((string) ($row->error ?? 'no error recorded'), 0, 120);
        }

        return $lines;
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @param array<string, list<string>> $findings */
    private function send(array $findings): void
    {
        $to = $this->settings->get('general', 'admin_notification_email')
            ?: $this->settings->get('general', 'support_email');

        if (! $to) {
            /*
             * ⚠️ SAID OUT LOUD RATHER THAN RETURNING QUIETLY. A report with
             * nowhere to go is the exact fault this command exists to end, and
             * it must not be the thing this command does.
             */
            $this->error('No admin notification email is set, so this report reached nobody.');
            Log::critical('Silent-failure report had no recipient.', ['findings' => array_keys($findings)]);

            return;
        }

        /*
         * ⚠️ A MAILABLE, NOT `Mail::raw`. MailFake::raw() is an empty method, so
         * a raw send is invisible to every test and "the alert goes out" would
         * be an unprovable claim about the thing whose entire job is to be
         * heard. See App\Mail\OperationalAlert.
         */
        $alert = new OperationalAlert(
            subjectLine: 'RealVows: something failed quietly',
            intro: 'Some things on '.config('app.url').' failed without anybody being told.',
            sections: $findings,
            outro: 'This message is only sent when something needs a person. If it did not '
                .'arrive yesterday, that is because there was nothing to say.',
        );

        try {
            $this->mail->apply();

            Mail::to($to)->send($alert);

            $this->newLine();
            $this->line("Reported to {$to}.");
        } catch (\Throwable $e) {
            // Never let the alert failing hide the finding itself.
            $this->error('Could not send the report: '.$e->getMessage());
            Log::critical('Silent-failure report could not be sent.', ['error' => $e->getMessage()]);
        }
    }

    private function lastReportedAt(): ?string
    {
        $path = storage_path(self::MARKER);

        if (! is_file($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        return $value === '' ? null : $value;
    }

    private function rememberNow(): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        @file_put_contents(storage_path(self::MARKER), now()->format('Y-m-d H:i:s'));
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            // A database that will not answer is itself worth reporting, and
            // the log reader above does not need one.
            return false;
        }
    }
}
