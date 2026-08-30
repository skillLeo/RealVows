<?php

namespace App\Support;

/**
 * ⚠️ THE ONLY TWO PEOPLE THIS PRODUCT SHOWS ANYWHERE.
 *
 * One Muslim couple, one Christian couple — the client's two audiences,
 * named directly. No other religion, no wider spread.
 *
 * The same pair appears in the hero AND in the couples section, deliberately.
 * Two separate sets would mean two licences to maintain and two slightly
 * different stories being told on one page; one pair is one story.
 *
 * ── The focal point is the whole point ────────────────────────────────────
 *
 * Both placements crop to a fixed aspect ratio, and `object-fit: cover` with
 * the default `center` crops blindly — it has no idea where the couple is in
 * a given frame, so it will slice through a face at one width and be fine at
 * another. That is what cut the top of the groom's headwear off on a phone.
 *
 * So each photograph carries its own hand-set focal point.
 *
 * ⚠️ SWAPPING AN IMAGE WITHOUT RE-CHECKING ITS FOCAL POINT SILENTLY
 * REINTRODUCES THE BUG. The procedure is in resources/photography/CREDITS.md,
 * and CouplesPhotographyTest fails if a file exists with no focal point
 * recorded for it.
 */
class Couples
{
    /**
     * Filenames are accepted under either naming, because the brief has used
     * both and neither of us should have to remember which. First match wins.
     *
     * @var array<string, array{names: list<string>, focal: string, alt: string, describes: string}>
     */
    public const PAIR = [
        'muslim' => [
            'names' => ['couple-muslim.jpg', 'rail-1.jpg'],
            /*
             * Measured, not guessed. The 3:4 crop discards 247px of a 2247px
             * frame — 11% — and this photograph fills almost all of it, so
             * both edges are tight and the value is a genuine compromise:
             *
             *   top     her hijab begins ~100px in, so anything above ~40%
             *           slices the crown of it. That headscarf is the only
             *           religious signal in the entire design; it does not
             *           get clipped.
             *   bottom  at 15% the line falls in the gap between their
             *           clasped hands (which end ~1990) and his hand resting
             *           on his knee (which starts ~2090), so it passes
             *           through cloth rather than through either.
             */
            'focal' => '50% 15%',
            'alt' => 'A couple sitting together at home, her arms around his shoulders',
            'describes' => 'A visibly Muslim couple — contemporary hijab, everyday clothes',
        ],
        'christian' => [
            'names' => ['couple-christian.jpg', 'rail-2.jpg'],
            /*
             * Both faces centered, waist-up framing, clear space above their
             * heads. The crop takes ~11% of the height (249px of 2249), and
             * 40% down the frame centers on both of them without cutting
             * headroom. At the 3:4 ratio this is the axis that decides the
             * output.
             */
            'focal' => '50% 40%',
            'alt' => 'A couple standing together in a park, both smiling',
            'describes' => 'A couple in everyday clothes, quiet and warm',
        ],
    ];

    /**
     * ⚠️ A STAND-IN IS ONLY ALLOWED WHERE ITS CONTENT IS ALREADY CORRECT.
     *
     * The Christian slot falls back to `hero-a.jpg`, which genuinely shows a
     * Christian wedding couple — the photograph is a placeholder for
     * licensing reasons, not for content reasons, so showing it is honest.
     *
     * The Muslim slot has NO fallback, and that is deliberate. The only image
     * available was `hero-b.jpg`, which shows a couple in Hindu wedding dress
     * — a red turban with a kalgi, and a swastika on the bride's lehenga. On
     * a product built for Muslim and Christian members, putting that in the
     * Muslim slot is not a neutral placeholder. It is the wrong statement,
     * made on the first screen anybody sees.
     *
     * A visibly empty frame says "this is not finished". A wrong photograph
     * says something about who this product is for, and says it confidently.
     * So that slot renders a sealed panel until the real photograph arrives.
     */
    private const FALLBACKS = [
        'muslim' => null,
        'christian' => null,
    ];

    /**
     * @return list<array{src: string|null, focal: string, alt: string, key: string, isPlaceholder: bool, describes: string}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::PAIR as $key => $couple) {
            $src = null;

            foreach ($couple['names'] as $name) {
                if (is_file(public_path("images/site/{$name}"))) {
                    $src = "images/site/{$name}";
                    break;
                }
            }

            $out[] = [
                'key' => $key,
                // Null renders a sealed panel rather than the wrong couple.
                'src' => $src ? self::versioned($src) : self::FALLBACKS[$key],
                'focal' => $couple['focal'],
                'alt' => $couple['alt'],
                'describes' => $couple['describes'],
                'isPlaceholder' => $src === null,
            ];
        }

        return $out;
    }

    /**
     * ⚠️ CACHE-BUSTING, AND IT IS NOT OPTIONAL.
     *
     * Hostinger's CDN caches images for seven days. Swapping a photograph on
     * the server therefore changes nothing a visitor sees — the old one keeps
     * being served from the edge, and it looks exactly like a failed deploy.
     * That happened: the origin held the new photograph while the live page
     * showed the previous one.
     *
     * Appending the file's modification time means a swapped file is a new
     * URL, so the CDN has nothing cached for it and fetches immediately. Every
     * future photograph swap now takes effect the moment the file lands, with
     * nothing to remember and nothing to purge.
     */
    private static function versioned(string $path): string
    {
        $full = public_path($path);

        return is_file($full) ? $path.'?v='.filemtime($full) : $path;
    }

    /** True while either photograph is still a stand-in. */
    public static function awaitingRealPhotography(): bool
    {
        foreach (self::all() as $couple) {
            if ($couple['isPlaceholder']) {
                return true;
            }
        }

        return false;
    }
}
