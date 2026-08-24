<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The handful of facts the public marketing pages need, projected out of the
 * database into a file they can read without touching it.
 *
 * ── ⚠️ WHY NOT JUST READ THE SETTING ──────────────────────────────────────
 *
 * The public pages are Blade. They load a stylesheet, a small vanilla script
 * and nothing else — no Vue, no Inertia, no hydration — because the home page
 * has to be fast on a mid-range phone over 4G. Adding a settings lookup to
 * decide whether to show a Pricing link would put a database round trip on the
 * one page that never needed one.
 *
 * It has already gone wrong once in exactly that shape: a free-period read on
 * every request turned the marketing home page into a 500 the moment the
 * settings table was unmigrated. A database fault took down the page that does
 * not depend on a database.
 *
 * ── ⚠️ WHY NOT THE CACHE ──────────────────────────────────────────────────
 *
 * Because on this host `CACHE_STORE=database`. "Cache it" and "query it" are
 * the same operation with different words, and a cache entry would have looked
 * like a fix while changing nothing. There is no Redis on Hostinger shared
 * hosting and there is not going to be.
 *
 * So: a plain PHP file returning an array. OPcache holds it in memory after
 * the first request, which makes the read genuinely free, and `require` on a
 * missing file is recoverable in a way a failed query is not.
 *
 * ── ⚠️ THE COLD PATH HIDES THINGS. IT DOES NOT GUESS ──────────────────────
 *
 * Note this answers a DIFFERENT question from FreePeriod::isActive(), and the
 * two have opposite safe defaults, which is exactly why they are separate:
 *
 *   FreePeriod::isActive()  — "should we charge?"  Unreadable → charge.
 *                             Being wrong is visible and refundable.
 *
 *   PublicState here        — "should the Pricing link render?"  Unreadable →
 *                             do not render it. A navigation link to a page
 *                             that 404s is worse than no link at all, and
 *                             during the free period /packages is a 404 BY
 *                             DESIGN.
 *
 * Both fail safe. They fail safe in opposite directions because they are
 * answering opposite questions, and collapsing them into one flag would make
 * one of the two wrong. DO NOT "SIMPLIFY" THIS INTO A SINGLE BOOLEAN.
 */
class PublicState
{
    /**
     * Kept out of the web root. `storage/` is writable on shared hosting and
     * is not served, so the projection cannot be fetched over HTTP.
     */
    /**
     * ⚠️ FIFTEEN MINUTES, AGAINST A WRITER THAT RUNS EVERY MINUTE.
     *
     * Fourteen consecutive missed runs are tolerated, and the longest a public
     * page can carry a stale promise is a quarter of an hour.
     */
    public const STALE_AFTER_SECONDS = 900;

    public static function path(): string
    {
        return storage_path('app/public-state.php');
    }

    /**
     * Write the projection.
     *
     * ⚠️ WRITTEN TO A TEMPORARY FILE AND RENAMED, never written in place. A
     * `require` that lands mid-write reads half a PHP file and fatals, and on
     * a busy page that is a white screen for whoever asked first. `rename()`
     * is atomic on the same filesystem.
     */
    public static function write(bool $freePeriodActive, ?int $foundingPlacesLeft = null): bool
    {
        $contents = "<?php\n\n"
            ."/*\n"
            ." * GENERATED FILE — DO NOT EDIT.\n"
            ." *\n"
            ." * Written by App\\Support\\PublicState so the public Blade pages can\n"
            ." * answer \"is the free period on?\" without a database query. Rewritten\n"
            ." * whenever settings are saved, and again every minute from the\n"
            ." * scheduler so a missed write repairs itself.\n"
            ." */\n\n"
            .'return '.var_export([
                'free_period' => $freePeriodActive,
                'founding_places_left' => $foundingPlacesLeft,
                'written_at' => now()->toIso8601String(),
            ], true).";\n";

        try {
            $path = self::path();
            File::ensureDirectoryExists(dirname($path));

            $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

            if (File::put($temporary, $contents) === false) {
                return false;
            }

            if (! @rename($temporary, $path)) {
                @unlink($temporary);

                return false;
            }

            /*
             * OPcache holds the OLD contents keyed on the path, so without
             * this the file is rewritten and every request keeps reading the
             * previous value until the cache expires. That is a stale
             * projection, which is the failure mode this class exists to
             * avoid.
             */
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('public state projection could not be written', [
                'error' => $e->getMessage(),
                'consequence' => 'The public pages will fall back to hiding the pricing link, '
                    .'which is safe but may be wrong once payments are live.',
            ]);

            return false;
        }
    }

    /**
     * ⚠️ SHOULD THE PRICING LINK RENDER?
     *
     * False during the free period, because /packages is deliberately a 404
     * then. False when the projection is missing or unreadable, because a link
     * to a 404 is worse than no link. True only when we positively know
     * payments are on.
     */
    public static function pricingLinkVisible(): bool
    {
        $state = self::read();

        if ($state === null) {
            return false;
        }

        return $state['free_period'] === false;
    }

    /**
     * Whether the free period is on, as far as the public pages know.
     *
     * ⚠️ A MISSING PROJECTION READS AS "FREE". That is the honest direction
     * for copy: telling somebody joining is free when it is not is a mistake we
     * would honour; telling them it costs when it is free loses the member and
     * nobody ever finds out.
     */
    public static function freePeriodActive(): bool
    {
        return self::read()['free_period'] ?? true;
    }

    /**
     * ⚠️ IS THE FOUNDING OFFER STILL TRUE?
     *
     * A missing or unreadable projection means NO, and that direction is the
     * opposite of freePeriodActive() above on purpose. "Joining is free" is a
     * standing statement about how the product runs and is safe to assume.
     * "You can be one of the first hundred" is a specific promise about a
     * specific number, and a promise must never be made on a guess.
     */
    public static function foundingOfferOpen(): bool
    {
        return self::foundingPlacesLeft() > 0;
    }

    /** Zero when unknown, so a template can only ever under-promise. */
    public static function foundingPlacesLeft(): int
    {
        $left = self::read()['founding_places_left'] ?? null;

        return is_int($left) && $left > 0 ? $left : 0;
    }

    /**
     * How old the projection is, in seconds, or null when there is not one.
     *
     * ⚠️ THE ADMIN PANEL ASKS THIS RATHER THAN RE-DERIVING THE RULE. A second
     * copy of "fifteen minutes" on a dashboard is a number that disagrees with
     * this one the day somebody tunes it.
     */
    public static function ageInSeconds(): ?int
    {
        $path = self::path();

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $state = require $path;
        } catch (Throwable) {
            return null;
        }

        $written = $state['written_at'] ?? null;

        if (! is_string($written) || $written === '') {
            return null;
        }

        try {
            return max(0, now()->diffInSeconds(\Illuminate\Support\Carbon::parse($written), false) * -1);
        } catch (Throwable) {
            return null;
        }
    }

    /** Whether the public pages are currently ignoring the projection on age. */
    public static function isStaleNow(): bool
    {
        $age = self::ageInSeconds();

        return $age === null || $age > self::STALE_AFTER_SECONDS;
    }

    private static function isStale(mixed $writtenAt): bool
    {
        if (! is_string($writtenAt) || $writtenAt === '') {
            /*
             * ⚠️ NO TIMESTAMP IS TREATED AS STALE. A projection that cannot say
             * when it was written cannot be shown to be current, and this is a
             * promise about a number.
             */
            return true;
        }

        try {
            $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($writtenAt), false) * -1;
        } catch (Throwable) {
            return true;
        }

        if ($age <= self::STALE_AFTER_SECONDS) {
            return false;
        }

        /*
         * ⚠️ THE STALE PATH IS LOUD, BECAUSE ITS CORRECT BEHAVIOUR IS INVISIBLE.
         *
         * Closing the band is the right outcome and it looks exactly like an
         * offer that genuinely ran out. Without this line the scheduler can be
         * dead for two days while real visitors are quietly refused an offer
         * that is still open, and the first symptom is signups that did not
         * happen.
         */
        Log::warning('Public state projection is stale and is being ignored.', [
            'age_seconds' => $age,
            'threshold_seconds' => self::STALE_AFTER_SECONDS,
            'consequence' => 'The founding band is closed and the pricing link is hidden on '
                .'every public page, whatever the database actually says.',
            'likely_cause' => 'The scheduler is not running, or realvows:project-public-state is '
                .'failing. Check the cron entry and the scheduler log.',
        ]);

        return true;
    }

    /** @return array{free_period: bool, founding_places_left: ?int, written_at: string}|null */
    private static function read(): ?array
    {
        $path = self::path();

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $state = require $path;
        } catch (Throwable) {
            return null;
        }

        if (! is_array($state) || ! array_key_exists('free_period', $state)) {
            return null;
        }

        /*
         * ⚠️ FAIL-CLOSED HANDLED UNREADABLE AND SAID NOTHING ABOUT STALE.
         *
         * A projection that cannot be parsed reads as closed, which is correct.
         * A projection that parses perfectly and was written three days ago
         * also reads as open, which is the same false statement arriving by a
         * different door: the scheduler dies, the file sits there, and the home
         * page keeps offering places that ran out on Tuesday.
         *
         * ⚠️ REJECTING ON AGE NEEDS NO NEW FAIL DIRECTION, WHICH IS WHY IT IS
         * HERE RATHER THAN IN EACH GETTER. Every caller already handles null
         * the way it should: freePeriodActive reads it as FREE, because
         * over-promising free is honourable; foundingOfferOpen reads it as
         * CLOSED, because a promise about a specific number must never be a
         * guess. Returning null on age gives each of them the direction it
         * already chose.
         *
         * ⚠️ FIFTEEN MINUTES AGAINST A ONE-MINUTE WRITER. That tolerates
         * fourteen consecutive missed runs — a reboot, a slow host, a deploy —
         * while capping the maximum lie at a quarter of an hour. Tighter and a
         * single stalled minute flickers the band off for real visitors.
         */
        if (self::isStale($state['written_at'] ?? null)) {
            return null;
        }

        $left = $state['founding_places_left'] ?? null;

        return [
            'free_period' => (bool) $state['free_period'],
            'founding_places_left' => is_numeric($left) ? (int) $left : null,
            'written_at' => (string) ($state['written_at'] ?? ''),
        ];
    }
}
