<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PowerTranzGateway;
use App\Services\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the payment gateway.
 *
 * ⚠️ Sandbox versus live is never decided here — that is configuration, read
 * by PowerTranzGateway from the settings table so the client can switch it
 * in his browser. The only thing this decides is real gateway versus fake.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app) {
            /*
             * Tests never touch the network. This is the one place that is
             * enforced, so no individual test has to remember to stub.
             */
            if ($app->environment('testing')) {
                return new FakeGateway;
            }

            /*
             * The review build has no merchant credentials and the client is
             * clicking through it. A fake gateway there means the checkout
             * flow is walkable end to end without a live account — and
             * because the fake is deterministic, what he sees is what the
             * real one will do.
             */
            $configured = $app->make(SettingsService::class)->get('payments', 'api_key');

            if (! $configured && $app->environment('local', 'demo')) {
                return new FakeGateway;
            }

            return $app->make(PowerTranzGateway::class);
        });
    }
}
