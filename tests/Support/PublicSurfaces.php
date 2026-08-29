<?php

namespace Tests\Support;

use App\Support\WhatsAppTemplates;
use Illuminate\Support\Facades\File;

/**
 * Every surface a person can read without being signed in, RENDERED.
 *
 * ⚠️ RENDERED, NOT READ OFF DISK, AND THAT IS THE WHOLE POINT.
 *
 * Two guards depend on this — the marriage-lead reader and the outbound-link
 * reader — and both would be wrong if they scanned source files:
 *
 *   · `CommonalityFinder` and `RationaleWriter` emit whole sentences that
 *     exist in no template. A source reader sees a clean repository.
 *
 *   · A WhatsApp body written `"…\n\nrealvows.skillleo.com/dashboard"` reads
 *     as the host `nrealvows.skillleo.com` in the file, because only PHP's
 *     parser knows those two characters are a newline. A source reader finds a
 *     hostname that does not exist and misses the one that does.
 *
 * So everything here comes back through the thing that actually produces it.
 */
class PublicSurfaces
{
    /**
     * The public web pages, by the path a member would type.
     *
     * ⚠️ A NEW PUBLIC ROUTE THAT IS NOT ADDED HERE IS A SURFACE NO GUARD
     * READS. `PublicSurfaceCoverageTest` compares this list against the
     * router and fails when they disagree, so the omission cannot be silent.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return [
            '/',
            '/how-it-works',
            '/about',
            '/questions',
            '/terms',
            '/privacy',
            '/community-guidelines',
            '/refunds',
        ];
    }

    /**
     * ⚠️ THE MARKETING PAGES THAT NOW CARRY THE CLIENT'S OWN WORDS — R10 §8,
     * 2026-08-29.
     *
     * These four render copy taken verbatim from the reference the client
     * supplied. His instruction was explicit: "I do not want our words. I want
     * the same words that they have. Every single thing." Our house rules on
     * dashes, semicolons, contractions, banned vocabulary and the soul-mate
     * register were written for copy WE wrote, and they cannot be applied to
     * copy he chose without silently overruling him.
     *
     * ⚠️ THIS IS A SUSPENSION, NOT A DELETION, AND THE DIFFERENCE MATTERS.
     *
     * Every one of those readers still runs, at full strength, over:
     *   · the legal pages below, which are ours and stay in our register
     *   · every authenticated surface
     *   · every email
     *   · the copy deck
     *
     * So the rules still hold everywhere the client did not overrule them, and
     * the day this copy is replaced with ours again, deleting one entry from
     * this list puts the guard straight back over it.
     *
     * ⚠️ WHAT IS NOT SUSPENDED, ON ANY SURFACE: the celestial forbidden-terms
     * reader, the payment-provider guard, the no-browsing rule and the
     * no-member-channel rule. Those exist because a sentence would be untrue or
     * unsafe, not because it would be off-register, and the client overruling
     * our style is not the client overruling arithmetic.
     *
     * @return list<string>
     */
    public static function clientWordedPaths(): array
    {
        return ['/', '/how-it-works', '/about', '/questions'];
    }

    /** Paths still held to the house style: our own writing. @return list<string> */
    public static function houseStyledPaths(): array
    {
        return array_values(array_diff(self::paths(), self::clientWordedPaths()));
    }

    /**
     * Rendered HTML for every public page, keyed by path.
     *
     * @return array<string, string>
     */
    public static function pages(callable $get): array
    {
        $rendered = [];

        foreach (self::paths() as $path) {
            $rendered[$path] = $get($path);
        }

        return $rendered;
    }

    /**
     * The seven WhatsApp bodies as Meta will send them — placeholders filled,
     * escapes resolved.
     *
     * @return array<string, string>
     */
    public static function whatsAppBodies(): array
    {
        $bodies = [];

        foreach (array_keys(WhatsAppTemplates::TEMPLATES) as $name) {
            $bodies["whatsapp:{$name}"] = WhatsAppTemplates::bodyFor($name);
        }

        return $bodies;
    }

    /**
     * The PWA manifest, which is a user-facing surface the moment somebody
     * adds RealVows to a home screen.
     */
    public static function manifest(): string
    {
        $path = public_path('manifest.webmanifest');

        return File::exists($path) ? File::get($path) : '';
    }
}
