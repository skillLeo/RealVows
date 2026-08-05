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
                            {--stdin : Read the API key then the webhook secret, one per line}
                            {--from= : The from address. Must be on a domain verified in Resend.}';

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
            $settings->set('mail', 'driver', 'resend');
            $settings->set('mail', 'resend_api_key', $apiKey);

            /*
             * ⚠️ THE SMTP FIELDS ARE STILL WRITTEN, and that is deliberate.
             *
             * The `resend` driver fills the connection in at runtime, so these
             * are not read. But somebody who later switches the driver to
             * Custom SMTP to debug something would otherwise find four empty
             * boxes and no way to get back — so the working values are left
             * where that person will look for them.
             */
            $settings->set('mail', 'host', 'smtp.resend.com');
            $settings->set('mail', 'port', '587');
            $settings->set('mail', 'username', 'resend');
            $settings->set('mail', 'password', $apiKey);
            $settings->set('mail', 'encryption', 'tls');

            /*
             * ⚠️ THE FROM ADDRESS MUST BE ON A DOMAIN VERIFIED IN RESEND, AND
             * A WRONG ONE REJECTS EVERY SINGLE SEND.
             *
             * This is not a cosmetic setting. Resend refuses any message whose
             * from-domain it has not verified, with a 403 — so an address left
             * over from the Hostinger SMTP era (contact@skillleo.com) means
             * mail silently stops the moment the provider changes, and the
             * error appears nowhere a member or the client would look.
             *
             * It is set when given, and only defaulted when empty. Overwriting
             * a deliberate choice on every deploy would silently change what
             * members see in their inbox.
             */
            if ($from = $this->option('from')) {
                $settings->set('mail', 'from_address', $from);
                $this->info("From address set to {$from}.");
            } elseif (! $settings->get('mail', 'from_address')) {
                $settings->set('mail', 'from_address', 'introductions@realvows.skillleo.com');
            }

            if (! $settings->get('mail', 'from_name')) {
                $settings->set('mail', 'from_name', 'RealVows');
            }

            /*
             * ⚠️ WARN WHEN THE FROM DOMAIN LOOKS UNVERIFIABLE.
             *
             * We cannot ask Resend which domains are verified — a send-only
             * key is refused on that endpoint. What we CAN do is notice when
             * the from-domain is not one we would have configured, and say so
             * rather than let the first real send fail.
             */
            $address = (string) $settings->get('mail', 'from_address');
            $domain = substr(strrchr($address, '@') ?: '', 1);

            if ($domain !== '' && ! str_contains($domain, 'realvows')) {
                $this->line('');
                $this->warn("  ⚠️  From address is {$address}.");
                $this->warn('  Resend rejects any message whose from-domain is not verified in');
                $this->warn('  YOUR Resend account, with a 403 — every send would fail. If this');
                $this->warn('  domain is not verified there, change it:');
                $this->warn('      --from=introductions@realvows.skillleo.com');
                $this->line('');
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
