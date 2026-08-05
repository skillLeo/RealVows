<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The last lines of the webhook log, readable without SSH.
 *
 * ⚠️ THIS EXISTS BECAUSE "IS THE WEBHOOK WORKING?" WAS UNANSWERABLE.
 *
 * Both webhooks write to `storage/logs/whatsapp-webhook.log`, which sits
 * behind the web-root guard and is therefore unreachable over HTTP — correctly,
 * since it records who messaged us and when. But that also meant the only way
 * to know whether a delivery event had ever arrived was to open an SSH session,
 * and "nothing has arrived" and "I cannot see whether anything arrived" are the
 * same output.
 *
 * That is the shape this project keeps being bitten by, so it gets a reader.
 *
 * ⚠️ IT PRINTS WHAT THE LOG HOLDS, WHICH IS ALREADY REDACTED. Phone numbers
 * are masked to four digits and message bodies replaced with a length before
 * anything is written. This command adds no exposure; it just makes an
 * existing file readable by the person who owns it.
 */
class ShowWebhookLog extends Command
{
    protected $signature = 'realvows:webhook-log
                            {--lines=40 : How many lines to show}
                            {--filter= : Only lines containing this string, e.g. resend}';

    protected $description = 'Show recent webhook activity';

    public function handle(): int
    {
        $path = storage_path('logs/whatsapp-webhook.log');

        if (! File::exists($path)) {
            $this->warn('No webhook log yet. Nothing has ever reached either webhook.');

            return self::SUCCESS;
        }

        $lines = array_values(array_filter(explode("\n", File::get($path)), 'strlen'));

        if ($filter = $this->option('filter')) {
            $lines = array_values(array_filter(
                $lines,
                fn (string $line) => stripos($line, $filter) !== false,
            ));
        }

        if ($lines === []) {
            $this->warn('Nothing matched. The log exists but holds no such lines.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('── '.count($lines).' matching line(s), newest last ──');
        $this->line('');

        foreach (array_slice($lines, -1 * max(1, (int) $this->option('lines'))) as $line) {
            $this->line('  '.$line);
        }

        $this->line('');

        return self::SUCCESS;
    }
}
