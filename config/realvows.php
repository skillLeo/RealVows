<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-click demo logins
    |--------------------------------------------------------------------------
    |
    | ⚠️ THIS IS AN AUTHENTICATION BYPASS. When enabled, anyone who can reach
    | the login page can sign in as any demo account without a password.
    |
    | It exists so the client can click through the product quickly during
    | review. It is locked behind TWO conditions that must BOTH hold: the
    | environment must be `local` or `demo`, and the flag must be switched on
    | deliberately. Either alone is not enough.
    |
    | ⚠️ `production` is deliberately NOT on that list, and that is the whole
    | safety property. The review build runs as APP_ENV=demo. The day this
    | becomes a real launch, changing APP_ENV to production closes the bypass
    | by itself — nobody has to remember a second step, and forgetting one
    | cannot leave every account reachable by a stranger.
    |
    | When it resolves to false the route is not registered at all — not
    | hidden, not protected, absent.
    |
    */

    'demo_logins' => env('REALVOWS_DEMO_LOGINS', false)
        && in_array(env('APP_ENV'), ['local', 'demo'], true),

    /*
    |--------------------------------------------------------------------------
    | Demo environment
    |--------------------------------------------------------------------------
    |
    | True on the review build. Drives the banner that stops anybody mistaking
    | this for the live product, and is the same switch the one-click logins
    | hang off — so the two can never disagree with each other.
    |
    */

    'is_demo' => env('APP_ENV') === 'demo',

    /*
    |--------------------------------------------------------------------------
    | Production hostnames
    |--------------------------------------------------------------------------
    |
    | ⚠️ THE HOSTS WHERE THE REVIEW TREATMENT MAY NEVER APPEAR, WHATEVER THE
    | ENVIRONMENT VARIABLE SAYS.
    |
    | App\Support\ReviewBuild consults this before showing the review banner,
    | the noindex tag or the disallow-everything robots.txt. It exists because
    | realvows.com shipped with all three: the server's .env still said
    | APP_ENV=demo from its staging days, and the client's live business was
    | telling Google not to index it.
    |
    | A hostname is the one fact that cannot be misconfigured into being wrong.
    | If somebody typed realvows.com, they are on production.
    |
    */

    'production_hosts' => env('REALVOWS_PRODUCTION_HOSTS', 'realvows.com,www.realvows.com'),

];
