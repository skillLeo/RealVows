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
 * REINTRODUCES THE BUG. The procedure is in public/images/site/CREDITS.md,
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
             * Set by eye against the real photograph. Both faces sit at
             * roughly 38% down the frame — the umbrellas above them take the
             * top third — so anything higher crops the heads and anything
             * lower loses the groom's face first.
             */
            'focal' => '50% 38%',
            'alt' => 'A couple together on their wedding day',
            'describes' => 'A visibly Muslim couple — nikah or walima dress',
        ],
        'christian' => [
            'names' => ['couple-christian.jpg', 'rail-2.jpg'],
            /*
             * Set by eye against the real file. Both faces sit at about 20%
             * down — veil and bouquet below them, greenery above — so the
             * crop keeps the heads whole at every width.
             */
            'focal' => '50% 20%',
            'alt' => 'A couple together on their wedding day',
            'describes' => 'A Christian or Western wedding couple',
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
