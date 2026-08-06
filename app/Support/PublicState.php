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

        $left = $state['founding_places_left'] ?? null;

        return [
            'free_period' => (bool) $state['free_period'],
            'founding_places_left' => is_numeric($left) ? (int) $left : null,
            'written_at' => (string) ($state['written_at'] ?? ''),
        ];
    }
}
