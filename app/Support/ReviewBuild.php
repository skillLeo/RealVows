<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * ⚠️ IS THIS A REVIEW BUILD? THE HOSTNAME OVERRULES THE ENVIRONMENT VARIABLE.
 *
 * The review build carries three things: a banner saying the platform is not
 * real, a noindex meta tag, and a robots.txt that disallows everything. All
 * three hung off APP_ENV === 'demo' alone.
 *
 * On 2026-08-29 realvows.com — the client's own live domain — was serving all
 * three, because the server's .env still said APP_ENV=demo from when the same
 * build was a staging site. The visible symptom was a banner reading "This is
 * not the live platform. Every member shown here is invented, and the sign-in
 * buttons are open to anyone."
 *
 * The banner was the least of it. The site was also telling every search engine
 * not to index it, and robots.txt said Disallow: / — so the client's business
 * could not be found on Google at all, and had not been able to since it went
 * live.
 *
 * ⚠️ WHY THE HOSTNAME AND NOT JUST A CORRECTED .env.
 *
 * Correcting the server is necessary and is being done separately. It is not
 * sufficient: a single environment variable, edited by hand, on a box nobody
 * checks, is what caused this. The production hostname is the one fact that
 * cannot be wrong — if a visitor typed realvows.com, they are on production,
 * whatever the box believes about itself.
 *
 * So the review treatment is now impossible on a production host, and the .env
 * fix becomes a tidy-up rather than the only thing standing between the client
 * and an unindexed site.
 *
 * ⚠️ THIS DOES NOT GATE THE ONE-CLICK LOGINS. Those hang off
 * config('realvows.demo_logins'), a separate flag, and when it is false the
 * route is not registered at all. Verified 404 on production. That separation
 * is deliberate and must stay: a banner going quiet must never be the thing
 * that opens a door.
 */
class ReviewBuild
{
    /**
     * Hosts that are the live product, whatever APP_ENV claims.
     *
     * @return list<string>
     */
    public static function productionHosts(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('realvows.production_hosts', ''))
        )));
    }

    /** Is the given host one the public reaches the real product on? */
    public static function isProductionHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        return in_array($host, array_map('strtolower', self::productionHosts()), true);
    }

    /**
     * True only where the review treatment is both configured AND safe to show.
     */
    public static function active(?string $host = null): bool
    {
        if (! (bool) config('realvows.is_demo')) {
            return false;
        }

        $host ??= Request::getHost();

        return ! self::isProductionHost($host);
    }
}
