<?php

/**
 * ⚠️ ERROR TRACKING. DORMANT UNTIL A DSN IS PASTED IN, EXACTLY LIKE THE AI
 * FEATURES.
 *
 * ── Why this exists at all ────────────────────────────────────────────────
 *
 * Thirty-two error-level lines sat unread on production. Reading them by hand
 * is what found twenty-two missing guardian photographs and a seeder that had
 * died under --no-dev. Both were recorded honestly, at the moment they
 * happened, into a file nobody opened.
 *
 * `realvows:report-silent-failures` now mails a digest each morning, which
 * closes that gap within a day. This closes it within seconds.
 *
 * ⚠️ WIRED NOW, WHILE IT IS CALM. The argument for doing it later — "revisit
 * when real members arrive" — puts the setup work at the exact moment somebody
 * is launching, distracted, and something is already breaking. A 24-hour digest
 * during a launch means reading tomorrow about members who left today.
 *
 * ⚠️ NO DSN MEANS NO SENTRY, AND NOTHING BREAKS. Empty is the shipped state.
 * The SDK does nothing, no request leaves this server, and the digest carries
 * on alone. Paste a DSN into SENTRY_LARAVEL_DSN and it starts reporting on the
 * next request with no other change.
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    /*
     * ⚠️ MEMBER DATA MUST NOT LEAVE THIS SERVER.
     *
     * `send_default_pii` false is the whole reason this is safe to enable on a
     * platform holding photographs, verification material and the details of
     * people who never signed up on their own initiative. With it on, Sentry
     * attaches request bodies, cookies and IP addresses to every event.
     *
     * DO NOT TURN THIS ON to make an error easier to debug. The stack trace is
     * the useful part and it is already here.
     */
    'send_default_pii' => false,

    'traces_sample_rate' => 0.0,
    'profiles_sample_rate' => 0.0,

    'breadcrumbs' => [
        'sql_bindings' => false,
    ],

    /*
     * The release, so an error can be tied to what was deployed. Falls back to
     * nothing rather than guessing, because a wrong release tag is worse than
     * an absent one when somebody is trying to work out which build broke.
     */
    'release' => env('SENTRY_RELEASE'),

    'environment' => env('APP_ENV', 'production'),
];
