<?php

namespace App\Providers;

use App\Services\Settings\SettingsService;
use App\Services\Verification\ManualVerificationService;
use App\Services\Verification\VerificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, so the per-request memoisation inside the
        // service is actually shared rather than rebuilt by every consumer.
        $this->app->singleton(SettingsService::class);

        // Verification is manual today. Binding the contract rather than the
        // class is what lets an automated provider replace it later without
        // the controller, the queue, or the UI changing at all.
        $this->app->bind(VerificationService::class, ManualVerificationService::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Auth endpoints are limited per IP AND per email address.
     *
     * Per IP alone lets someone spread attempts across many accounts from one
     * address; per email alone lets a botnet hammer one account from many
     * addresses. Both together close each other's gap.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.mb_strtolower($email)),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.mb_strtolower($email)),
            ];
        });

        /*
         * Starting a checkout creates a row and talks to a payment gateway.
         * Somebody holding down the button should not be able to open fifty
         * transactions, and a gateway that sees a burst from one merchant
         * starts asking questions.
         */
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)
            ->by('user:'.$request->user()?->id));

        /*
         * Unlocking spends money. The unique index already makes a double
         * charge impossible, so this is not the guarantee — it is here so a
         * stuck button cannot generate a hundred caught exceptions a second.
         */
        RateLimiter::for('unlock', fn (Request $request) => Limit::perMinute(10)
            ->by('user:'.$request->user()?->id));
    }
}
