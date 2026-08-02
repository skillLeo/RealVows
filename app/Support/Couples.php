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
             * Set by eye. The couple's faces are high in this frame, around
             * 18% — they are kissing, and the crop is set so the embrace is the
             * first thing you see rather than the dress below it —
             * so the crop is biased well up.
             */
            'focal' => '50% 18%',
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
                'src' => $src ?? self::FALLBACKS[$key],
                'focal' => $couple['focal'],
                'alt' => $couple['alt'],
                'describes' => $couple['describes'],
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
