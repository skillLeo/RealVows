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
    | review. It is therefore locked behind TWO conditions that must BOTH
    | hold: the application must be running in the local environment, and the
    | flag must be switched on deliberately. Either one alone is not enough,
    | because a single misconfigured .env on a live server would otherwise
    | hand every account away.
    |
    | On staging or production this resolves to false and the route does not
    | exist at all — not hidden, not protected, absent.
    |
    */

    'demo_logins' => env('REALVOWS_DEMO_LOGINS', false) && env('APP_ENV') === 'local',

];
