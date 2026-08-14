<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ⚠️ ERROR TRACKING MUST NEVER CARRY MEMBER DATA OFF THIS SERVER.
 *
 * Sentry is wired and dormant. The moment somebody pastes a DSN in, every
 * uncaught exception starts leaving this machine — and this platform holds
 * photographs, verification material, and the details of trusted contacts who
 * never signed up on their own initiative.
 *
 * ⚠️ `send_default_pii` IS THE ONE SETTING THAT DECIDES THIS. With it on,
 * request bodies, cookies and IP addresses ride along with every event. It is
 * exactly the flag somebody turns on at 2am to make one error easier to read,
 * and the reason it must fail a test rather than a code review.
 *
 * The stack trace is the useful part, and it is already there without it.
 */
class ErrorTrackingIsSafeTest extends TestCase
{
    public function test_personal_information_is_never_attached_to_an_error(): void
    {
        $this->assertFalse(
            config('sentry.send_default_pii'),
            'Sentry would attach request bodies, cookies and IP addresses to every reported '
                .'error. On this platform that means member data leaving the server to make a '
                .'stack trace marginally easier to read.',
        );

        $this->assertFalse(
            config('sentry.breadcrumbs.sql_bindings'),
            'SQL bindings would carry the actual values queried — email addresses, phone '
                .'numbers, photograph paths — into every breadcrumb trail.',
        );
    }

    public function test_it_ships_switched_off(): void
    {
        /*
         * ⚠️ EMPTY IS THE SHIPPED STATE, and it has to stay that way. A DSN
         * committed to the repository would start reporting from every
         * developer machine and every CI run into the client's project, which
         * is both noise and a small data leak.
         */
        /*
         * ⚠️ THIS ASSERTED `assertNull` AND PASSED ON MY MACHINE ONLY.
         *
         * My own .env has no Sentry key at all, so env() returned null and it
         * was green. CI copies .env.example, which now carries
         * `SENTRY_LARAVEL_DSN=` — an empty STRING — and it failed there
         * immediately.
         *
         * Unset and set-to-empty both mean "Sentry is off", and only one of
         * them is null. So the assertion was about my laptop rather than about
         * the property.
         *
         * That is the eleventh failure shape reappearing inside a test written
         * an hour after documenting it, in a file about safety. Normalised here
         * so it says the same thing everywhere.
         */
        $this->assertSame(
            '',
            trim((string) config('sentry.dsn')),
            'A Sentry DSN is set in this environment. That is correct on the server and wrong '
                .'in a committed .env.example, in CI, or on a developer machine.',
        );
    }

    public function test_the_configuration_survives_having_no_dsn(): void
    {
        /*
         * The thing that actually matters on the day this is dormant: nothing
         * about a missing DSN may break a request. A tracker that throws when
         * unconfigured would take down the site it was installed to watch.
         */
        $this->get('/')->assertOk();
        $this->get('/privacy')->assertOk();
    }
}
