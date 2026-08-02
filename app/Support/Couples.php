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
            // Provisional. Re-set by eye when the real file lands.
            'focal' => '50% 28%',
            'alt' => 'A couple together on their wedding day',
            'describes' => 'A visibly Muslim couple — nikah or walima dress',
        ],
        'christian' => [
            'names' => ['couple-christian.jpg', 'rail-2.jpg'],
            'focal' => '50% 25%',
            'alt' => 'A couple together on their wedding day',
            'describes' => 'A Christian or Western wedding couple',
        ],
    ];

    /**
     * Standing in until the real files arrive, so every page is reviewable
     * now and needs no code change when they land.
     *
     * ⚠️ `hero-b.jpg` shows a couple in Hindu/Sikh wedding dress and MUST NOT
     * ship. It is here only so the layout can be judged.
     */
    private const PLACEHOLDERS = [
        'muslim' => 'images/site/hero-b.jpg',
        'christian' => 'images/site/hero-a.jpg',
    ];

    /**
     * @return list<array{src: string, focal: string, alt: string, key: string, isPlaceholder: bool}>
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
                'src' => $src ?? self::PLACEHOLDERS[$key],
                'focal' => $couple['focal'],
                'alt' => $couple['alt'],
                'isPlaceholder' => $src === null,
            ];
        }

        return $out;
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
