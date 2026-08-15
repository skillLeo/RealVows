<?php

namespace App\Providers;

use App\Listeners\RefuseUndeliverableAddresses;
use App\Services\Settings\SettingsService;
use App\Services\Verification\ManualVerificationService;
use App\Services\Verification\VerificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
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
        /*
         * ⚠️ ONE POINT EVERY PUBLIC PAGE READS, SO A NEW PAGE CANNOT FORGET.
         *
         * The alternative is each template calling the setting itself, which is
         * a call somebody omits on the eighth page — and the failure there is a
         * public promise of a channel the platform cannot use.
         */
        \Illuminate\Support\Facades\View::composer('public.*', function ($view) {
            $view->with('whatsappDelivery', app(\App\Support\WhatsAppDelivery::class)->isOn());
        });

        $this->configureRateLimiting();

        /*
         * ⚠️ ONE POINT EVERY MESSAGE PASSES THROUGH, so a send site added later
         * cannot forget it. See RefuseUndeliverableAddresses for what the daily
         * bounces to @realvows.test were costing a domain with no sending
         * reputation to spare.
         */
        Event::listen(MessageSending::class, RefuseUndeliverableAddresses::class);
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

        /*
         * ⚠️ THIS LIMIT PROTECTS US FROM A STRANGER AND CAN ALSO CUT OFF META.
         *
         * Every other limiter here guards something expensive. This one guards
         * an endpoint that writes a log line — so the thing it really prevents
         * is an unknown caller filling the disk, not load.
         *
         * The cost of getting it wrong runs the other way. Meta delivers in
         * bursts, counts a 429 as a failed delivery, retries with backoff, and
         * disables a subscription that keeps failing. A limit set too low
         * therefore does not slow WhatsApp down, it silently switches it off,
         * and the symptom is members not being told about their matches.
         *
         * 100/minute was specified and is far above anything this product will
         * produce. Raise it before suspecting anything else if deliveries ever
         * start going missing.
         */
        RateLimiter::for('whatsapp-webhook', fn (Request $request) => Limit::perMinute(100)
            ->by('ip:'.$request->ip()));
    }
}
