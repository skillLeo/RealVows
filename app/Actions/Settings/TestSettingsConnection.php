<?php

namespace App\Actions\Settings;

use App\Services\Settings\RuntimeMailConfigurator;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class TestSettingsConnection
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RuntimeMailConfigurator $mailConfigurator,
    ) {}

    /**
     * Genuinely attempt the connection and report what actually happened.
     *
     * The client is non-technical and cannot read a stack trace, but he can
     * read "Connection refused" or "Authentication failed" — so the real
     * provider message is passed through rather than replaced with a generic
     * "something went wrong", which would tell him nothing he can act on.
     *
     * @return array{ok: bool, message: string}
     */
    public function handle(string $group): array
    {
        return match ($group) {
            'mail' => $this->testMail(),
            'storage' => $this->testStorage(),
            default => [
                'ok' => false,
                'message' => 'There is nothing to test in this group yet.',
            ],
        };
    }

    private function testMail(): array
    {
        $to = $this->settings->get('mail', 'from_address')
            ?: $this->settings->get('general', 'admin_notification_email');

        if (! $to) {
            return [
                'ok' => false,
                'message' => 'Set a from address or an admin notification email first, so there is somewhere to send the test.',
            ];
        }

        try {
            $this->mailConfigurator->apply();

            Mail::raw(
                "This is a test message from RealVows.\n\nIf you are reading it, your email settings are working.",
                fn ($message) => $message->to($to)->subject('RealVows test message')
            );

            return [
                'ok' => true,
                'message' => "Sent a test message to {$to}. Check that inbox.",
            ];
        } catch (TransportExceptionInterface $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function testStorage(): array
    {
        $driver = $this->settings->get('storage', 'driver', 'local');

        try {
            $disk = Storage::disk($driver === 'local' ? 'local' : $driver);
            $probe = 'realvows-connection-test-'.bin2hex(random_bytes(4)).'.txt';

            $disk->put($probe, 'RealVows connection test');
            $readBack = $disk->get($probe);
            $disk->delete($probe);

            if ($readBack !== 'RealVows connection test') {
                return [
                    'ok' => false,
                    'message' => 'The file was written but read back differently. Check the bucket and permissions.',
                ];
            }

            return [
                'ok' => true,
                'message' => 'Wrote, read and deleted a test file successfully.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
