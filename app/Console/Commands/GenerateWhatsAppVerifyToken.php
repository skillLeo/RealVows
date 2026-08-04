<?php

namespace App\Console\Commands;

use App\Actions\RecordAuditLog;
use App\Services\Settings\SettingsService;
use App\Support\WhatsAppWebhook;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;

class GenerateWhatsAppVerifyToken extends Command
{
    /**
     * Mint the shared secret Meta echoes back when verifying the callback URL.
     *
     * ⚠️ THIS COMMAND EXISTS SO THE TOKEN NEVER HAS TO BE INVENTED BY HAND.
     *
     * The specification for the webhook supplied a literal token to compare
     * against — a readable phrase with a number on the end, written into the
     * code. Three things are wrong with that and only the first is obvious:
     * it is guessable, it is identical on every deployment, and it is in the
     * git history permanently, so rotating it later does not actually remove
     * it from anywhere it has already been.
     *
     * A generated value stored in the settings table is encrypted at rest,
     * differs per environment, and can be rotated by running this again.
     *
     * ⚠️ IT IS PRINTED EXACTLY ONCE. The settings screen masks secrets, so
     * after this command finishes there is no way to read it back — which is
     * the point, and is also why it stops and asks before replacing one that
     * already works.
     */
    protected $signature = 'whatsapp:verify-token
                            {--force : Replace an existing token without asking}';

    protected $description = 'Generate and store the WhatsApp webhook verify token';

    public function handle(SettingsService $settings, WhatsAppWebhook $webhook, RecordAuditLog $audit): int
    {
        if ($webhook->verifyToken() !== null && ! $this->option('force')) {
            $this->warn('A verify token is already stored.');
            $this->line('');
            $this->line('  Replacing it will break the existing Meta subscription until the new');
            $this->line('  value is pasted back into the callback configuration.');
            $this->line('');

            if (! confirm('Replace it?', default: false)) {
                $this->info('Left alone.');

                return self::SUCCESS;
            }
        }

        // 48 URL-safe characters. Meta accepts any string; this is long enough
        // that guessing it is not a strategy and short enough to paste.
        $token = Str::random(48);

        if (! $settings->set('whatsapp', 'verify_token', $token)) {
            $this->error('Could not store the token. Has the settings seeder been run?');

            return self::FAILURE;
        }

        // The value never goes near the audit trail — only the fact of it.
        $audit->handle('settings.updated', null, [
            'group' => 'whatsapp',
            'changes' => ['verify_token' => ['from' => null, 'to' => $audit->fingerprint($token)]],
        ]);

        $url = rtrim((string) config('app.url'), '/').'/webhook/whatsapp';

        $this->line('');
        $this->info('Verify token generated. It is shown once and cannot be read back.');
        $this->line('');
        $this->line('  Callback URL   '.$url);
        $this->line('  Verify token   '.$token);
        $this->line('');
        $this->line('  Paste both into Meta → WhatsApp → Configuration → Edit, then press');
        $this->line('  "Verify and save". The endpoint answers the challenge automatically.');
        $this->line('');

        if (! str_starts_with($url, 'https://')) {
            $this->warn('  ⚠️  APP_URL is not https. Meta will not accept a plain http callback URL.');
            $this->line('');
        }

        return self::SUCCESS;
    }
}
