<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

/**
 * Point the mail settings at Resend.
 *
 * ⚠️ SMTP, NOT THE API. Resend's SMTP endpoint takes the same API key as a
 * password, so the existing settings-driven RuntimeMailConfigurator works
 * unchanged and no new dependency enters the project. The webhook — which is
 * the part that actually gives us bounce data — is independent of how the
 * message is handed over.
 *
 * ⚠️ VALUES ARRIVE ON STDIN, NEVER IN ARGV. On a shared host any other account
 * can read a command line out of the process list.
 */
class ConfigureResend extends Command
{
    protected $signature = 'realvows:configure-resend
                            {--stdin : Read the API key then the webhook secret, one per line}';

    protected $description = 'Point the mail settings at Resend';

    public function handle(SettingsService $settings): int
    {
        if (! $this->option('stdin')) {
            $this->error('Refusing to take credentials as arguments. Use --stdin.');

            return self::FAILURE;
        }

        $apiKey = trim((string) fgets(STDIN));
        $webhookSecret = trim((string) fgets(STDIN));

        if ($apiKey !== '') {
            /*
             * Resend's fixed SMTP coordinates. The username is the literal
             * string "resend" for every account — it is not the API key and
             * not an address.
             */
            $settings->set('mail', 'driver', 'smtp');
            $settings->set('mail', 'host', 'smtp.resend.com');
            $settings->set('mail', 'port', '587');
            $settings->set('mail', 'username', 'resend');
            $settings->set('mail', 'password', $apiKey);
            $settings->set('mail', 'encryption', 'tls');

            /*
             * ⚠️ ONLY SET IF EMPTY. The client may have chosen a from-address
             * already, and overwriting it on every deploy would silently
             * change what members see in their inbox.
             */
            if (! $settings->get('mail', 'from_address')) {
                $settings->set('mail', 'from_address', 'introductions@realvows.skillleo.com');
            }

            if (! $settings->get('mail', 'from_name')) {
                $settings->set('mail', 'from_name', 'RealVows');
            }

            $this->info('Mail settings pointed at Resend over SMTP.');
        }

        if ($webhookSecret !== '') {
            $settings->set('mail', 'webhook_secret', $webhookSecret);
            $this->info('Webhook signing secret stored. Delivery events will now be acted on.');
        }

        if ($apiKey === '' && $webhookSecret === '') {
            $this->warn('Nothing arrived on stdin. Nothing changed.');
        }

        return self::SUCCESS;
    }
}
