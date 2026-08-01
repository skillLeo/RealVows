<?php

namespace App\Providers;

use App\Services\Settings\SettingsService;
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
    }
}
