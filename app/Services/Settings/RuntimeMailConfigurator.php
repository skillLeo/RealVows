<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class RuntimeMailConfigurator
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Push the database mail settings into the live config.
     *
     * Mail must read from settings at RUNTIME, not from config at boot.
     * The client changes an SMTP password in the browser and the very next
     * email uses it — without a deploy, a config:clear, or a phone call to
     * a developer, none of which he can do.
     *
     * Called before anything is sent rather than in a service provider, so a
     * database read never sits in the boot path of every request on shared
     * hosting.
     */
    public function apply(): void
    {
        $driver = $this->settings->get('mail', 'driver');

        if (! $driver) {
            return;
        }

        Config::set('mail.default', $driver);

        if ($driver === 'smtp') {
            Config::set('mail.mailers.smtp', array_filter([
                'transport' => 'smtp',
                'host' => $this->settings->get('mail', 'host'),
                'port' => $this->settings->int('mail', 'port', 587),
                'username' => $this->settings->get('mail', 'username'),
                'password' => $this->settings->get('mail', 'password'),
                'encryption' => $this->settings->get('mail', 'encryption') ?: null,
                'timeout' => 15,
            ], fn ($value) => $value !== null));
        }

        $from = $this->settings->get('mail', 'from_address');

        if ($from) {
            Config::set('mail.from', [
                'address' => $from,
                'name' => $this->settings->get('mail', 'from_name') ?: 'RealVows',
            ]);
        }

        // Drop any mailer the container already built against the old config.
        Mail::purge($driver);
    }
}
