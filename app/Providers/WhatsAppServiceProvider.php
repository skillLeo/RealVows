<?php

namespace App\Providers;

use App\Services\Settings\SettingsService;
use App\Services\WhatsApp\LogSender;
use App\Services\WhatsApp\MetaCloudSender;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the WhatsApp sender.
 *
 * Deliberately the same shape as PaymentServiceProvider, including the
 * reasoning, because the failure it prevents is the same one: a settings
 * screen that names a provider while the container hands every send to a
 * stand-in, and nothing on screen saying so.
 */
class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppSender::class, function ($app) {
            /*
             * ⚠️ TESTS NEVER REACH THE NETWORK, ENFORCED HERE RATHER THAN
             * REMEMBERED PER TEST. A test that forgets to fake would otherwise
             * send a real WhatsApp message to a real number, which unlike a
             * stray HTTP call cannot be taken back.
             */
            if ($app->environment('testing')) {
                return new LogSender;
            }

            $token = $app->make(SettingsService::class)->get('whatsapp', 'access_token');

            /*
             * No token means no Meta account yet. The stand-in keeps the whole
             * flow walkable on the review build rather than throwing halfway
             * through a member's first mutual match.
             */
            if (! $token) {
                return new LogSender;
            }

            return $app->make(MetaCloudSender::class);
        });
    }
}
