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
            '/terms',
            '/privacy',
            '/community-guidelines',
            '/refunds',
        ];
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
