<?php

namespace App\Support;

/**
 * ⚠️ THE HERO MOSAIC — ONE MANIFEST FOR SEVEN PHOTOGRAPHS.
 *
 * Seven tiles on an aligned grid, not a scatter. The client shared a tilted
 * scrapbook photo-wall and a hexagon honeycomb as references; both were taken
 * for the IDEA (many moments at once rather than two) and rejected for the
 * execution. Hexagons break `--r`, which is a locked token, and rotation with
 * drop shadows reads as a corkboard. This product's register is a letterpress
 * wedding invitation. Varied spans on a strict grid give the same mosaic
 * richness while staying architectural — and it is the only version that
 * survives collapsing onto a phone.
 *
 * ── Why the tile mix is what it is ────────────────────────────────────────
 *
 * Five couples and two detail shots, not seven couples. Seven faces reads as
 * a stock library; five faces and two quiet details reads as a designed page.
 * The details also carry no religion-signalling risk at all, so they cannot
 * be got wrong — which matters, because getting the signal wrong has already
 * cost this project two rounds.
 *
 * ── The three layouts are named, not derived ──────────────────────────────
 *
 * Seven tiles at 375px would put each face in roughly 150px and no face
 * survives that. Mobile therefore DROPS tiles rather than shrinking all of
 * them, and the tiles it drops are chosen here rather than by whatever
 * happens to reflow. An unreadable face is worse than one fewer photograph.
 *
 * Grid placement lives in CSS because three breakpoints cannot be expressed
 * in an inline style. It is recorded here as well, and MosaicTest fails if
 * the two disagree — so this file stays authoritative without pretending it
 * is the thing the browser reads.
 */
class Mosaic
{
    /** Columns in each named layout. Rows are always three. */
    public const COLUMNS = ['desktop' => 4, 'tablet' => 3, 'mobile' => 2];

    /**
     * `grid` is `row-start / column-start / row-end / column-end`, matching
     * the CSS `grid-area` shorthand exactly so a test can compare them
     * character for character. A null means the tile is not rendered at that
     * breakpoint at all.
     *
     * @var array<string, array{
     *     file: string, kind: string, signal: string, focal: string,
     *     brief: string, photographer: ?string, licence: ?string, source: ?string,
     *     grid: array<string, ?string>, span: array<string, int>
     * }>
     */
    public const TILES = [
        /*
         * ⚠️ THE ANCHOR, AND THE CLIENT NAMED IT DIRECTLY — he sent this
         * photograph twice to be sure. The tall arch in column two, full
         * height, and the only visibly Muslim couple in the set. That makes
         * its position load-bearing rather than aesthetic: the phone drops to
         * five tiles and this is what keeps the Muslim signal on screen.
         */
        'a' => [
            'file' => 'couple-1.jpg',
            'kind' => 'couple',
            'signal' => 'muslim',
            'shape' => 'arch',
            'focal' => '50% 18%',
            'brief' => 'Muslim couple at home, contemporary hijab, everyday clothes.',
            'photographer' => 'Pavel Danilyuk',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/8525680/',
            'column' => 1,
            'row' => 0,
            'grid' => ['desktop' => '1 / 2 / 7 / 3', 'tablet' => '1 / 1 / 7 / 2', 'mobile' => '1 / 1 / 4 / 2'],
            'px' => ['desktop' => 220, 'tablet' => 290, 'mobile' => 160],
        ],

        /*
         * ⚠️ THE CLEAREST CHRISTIAN SIGNAL IN THE SET — a stone cross above a
         * church door, in frame, behind them. Nothing else here states a
         * faith that plainly, so it takes the second arch and survives to
         * mobile.
         *
         * ⚠️ Its arch is desktop and tablet only. On a phone it becomes one
         * of three small squares, and an arch that short reads as a lozenge
         * rather than as architecture.
         */
        'b' => [
            'file' => 'couple-5.jpg',
            'kind' => 'couple',
            'signal' => 'neutral',
            'shape' => 'arch',
            'focal' => '50% 26%',
            'brief' => 'Couple in coats on a street, foreheads close, reserved.',
            'photographer' => 'Gustavo Fring',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/7155863/',
            'column' => 2,
            'row' => 0,
            'grid' => ['desktop' => '1 / 3 / 5 / 4', 'tablet' => '1 / 2 / 5 / 3', 'mobile' => '1 / 2 / 2 / 3'],
            'px' => ['desktop' => 180, 'tablet' => 260, 'mobile' => 160],
        ],

        /*
         * ⚠️ THE TIGHTEST CROP IN THE SET, IN A SMALL TILE THAT SURVIVES TO
         * MOBILE. Deliberate pairing — a small tile is exactly where a face
         * fails, so it gets the photograph whose faces are already largest in
         * their own frame.
         */
        'c' => [
            'file' => 'couple-2.jpg',
            'kind' => 'couple',
            'signal' => 'christian',
            'shape' => 'square',
            'focal' => '50% 40%',
            'brief' => 'Couple outside a church, cross visible above the door.',
            'photographer' => 'cottonbro studio',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/6284608/',
            'column' => 2,
            'row' => 1,
            'grid' => ['desktop' => '5 / 3 / 7 / 4', 'tablet' => '5 / 2 / 7 / 3', 'mobile' => '2 / 2 / 3 / 3'],
            'px' => ['desktop' => 180, 'tablet' => 260, 'mobile' => 160],
        ],

        /*
         * ⚠️ PROVENANCE UNRECORDED. The park photograph the client supplied
         * directly, under a hashed filename, so there is no photographer and
         * no source URL to record. Almost certainly Pexels — but "almost
         * certainly" is not a licence record. Flagged rather than guessed.
         */
        'd' => [
            'file' => 'couple-3.jpg',
            'kind' => 'couple',
            'signal' => 'neutral',
            'shape' => 'square',
            'focal' => '50% 30%',
            'brief' => 'Couple laughing, close crop, both faces large in frame.',
            'photographer' => 'Polina Tankilevitch',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/7741572/',
            'column' => 3,
            'row' => 0,
            'grid' => ['desktop' => '1 / 4 / 3 / 5', 'tablet' => '1 / 3 / 3 / 4', 'mobile' => '3 / 2 / 4 / 3'],
            'px' => ['desktop' => 150, 'tablet' => 240, 'mobile' => 160],
        ],

        // The wide tile under the arch on a phone, spanning both columns.
        'e' => [
            'file' => 'couple-4.jpg',
            'kind' => 'couple',
            'signal' => 'neutral',
            'shape' => 'square',
            'focal' => '50% 38%',
            'brief' => 'Couple in a park, everyday clothes, waist-up.',
            'photographer' => null,
            'licence' => null,
            'source' => null,
            'column' => 0,
            'row' => 0,
            'grid' => ['desktop' => '2 / 1 / 4 / 2', 'tablet' => '3 / 3 / 5 / 4', 'mobile' => '4 / 1 / 5 / 3'],
            'px' => ['desktop' => 140, 'tablet' => 240, 'mobile' => 330],
        ],

        // Landscape source. Dropped on mobile, so it can afford to be the
        // widest composition of the set.
        'f' => [
            'file' => 'couple-6.jpg',
            'kind' => 'couple',
            'signal' => 'neutral',
            'shape' => 'square',
            'focal' => '50% 30%',
            'brief' => 'Couple in coats, landscape framing, quiet register.',
            'photographer' => 'Gustavo Fring',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/7155841/',
            'column' => 3,
            'row' => 1,
            'grid' => ['desktop' => '3 / 4 / 5 / 5', 'tablet' => '5 / 3 / 7 / 4', 'mobile' => null],
            'px' => ['desktop' => 150, 'tablet' => 240, 'mobile' => 0],
        ],

        /*
         * ⚠️ DESKTOP ONLY, AND THAT IS THE POINT.
         *
         * The client rejected this photograph in the two-up hero because its
         * jewelled headpiece and gold beading read as Eastern styling, making
         * the pair look like two Muslim couples. In a seven-tile mosaic that
         * objection is much weaker — one small tile among six, three of them
         * unambiguously Western — but it is not gone. So it takes the
         * smallest tile, is the first thing dropped, and never appears on a
         * phone at all.
         */
        'g' => [
            'file' => 'couple-7.jpg',
            'kind' => 'couple',
            'signal' => 'either',
            'shape' => 'square',
            'focal' => '50% 40%',
            'brief' => 'Bride and groom, warm tone. Smallest tile, desktop only.',
            'photographer' => 'Chidy',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/24051124/',
            'column' => 3,
            'row' => 2,
            'grid' => ['desktop' => '5 / 4 / 7 / 5', 'tablet' => null, 'mobile' => null],
            'px' => ['desktop' => 150, 'tablet' => 0, 'mobile' => 0],
        ],
        /*
         * ⚠️ THE ONLY TILE THAT IS NOT A COUPLE, AND THE REASON IS
         * COMPOSITIONAL RATHER THAN THEMATIC.
         *
         * Column one held one small square floating alone in a field of
         * oxblood, which read as a stray rather than as a column. It needed a
         * partner beneath it — but an eighth couple would have tipped the
         * arrangement back toward a stock library, which is the exact failure
         * the seven distinct photographs were chosen to avoid.
         *
         * So it is a detail: clasped hands, cropped out of the anchor
         * photograph in column two. Same couple, same shoot, same light, so
         * it belongs to the set by construction rather than by grading — and
         * a detail is the one thing in a grid of faces that cannot be got
         * wrong.
         *
         * ⚠️ Desktop only. The phone drops to five tiles because faces stop
         * being legible below about 160px, and a detail is the first thing
         * that should go when space is short.
         */
        'h' => [
            'file' => 'couple-8.jpg',
            'kind' => 'detail',
            'signal' => 'neutral',
            'shape' => 'square',
            'focal' => '50% 45%',
            'brief' => 'Clasped hands. A detail crop from the anchor photograph.',
            'photographer' => 'Pavel Danilyuk',
            'licence' => 'Pexels License',
            'source' => 'https://www.pexels.com/photo/8525680/',
            'column' => 0,
            'row' => 1,
            'grid' => ['desktop' => '4 / 1 / 6 / 2', 'tablet' => null, 'mobile' => null],
            'px' => ['desktop' => 150, 'tablet' => 0, 'mobile' => 0],
        ],
    ];


    /**
     * ⚠️ STAND-INS, AND THE COMPOSITION IS THE ONLY THING THEY ARE FOR.
     *
     * The two approved photographs stand in across all seven tiles so the
     * arrangement can be approved before seven files are sourced. They carry
     * their own hand-set focal points, because a stand-in cropped badly makes
     * the layout look broken and the layout is the thing being judged.
     *
     * Every tile is flagged as a placeholder while this is in force, so the
     * pill stays visible and nobody mistakes this for finished photography.
     */
    private const STAND_INS = [
        'muslim' => ['file' => 'couple-muslim.jpg', 'focal' => '50% 15%'],
        'christian' => ['file' => 'couple-christian.jpg', 'focal' => '50% 40%'],
    ];

    /** Widths generated for each photograph. See `php artisan images:derive`. */
    public const WIDTHS = [320, 640, 960, 1400];

    /**
     * @return list<array{
     *     key: string, src: ?string, webp: ?string, srcset: ?string, webpset: ?string,
     *     sizes: string, focal: string, kind: string, brief: string,
     *     isPlaceholder: bool, column: int, row: int, shape: string, eager: bool
     * }>
     */
    public static function tiles(): array
    {
        $out = [];
        $alternate = 0;

        foreach (self::TILES as $key => $tile) {
            $real = self::resolve($tile['file']);
            $focal = $tile['focal'];
            $isPlaceholder = $real === null;

            if ($isPlaceholder) {
                $signal = $tile['signal'];

                // Details and the flexible slot alternate, so the preview does
                // not show the same face six times in a row.
                if (! isset(self::STAND_INS[$signal])) {
                    $signal = ($alternate++ % 2 === 0) ? 'muslim' : 'christian';
                }

                $stand = self::STAND_INS[$signal];
                $real = self::resolve($stand['file']);
                $focal = $stand['focal'];
            }

            $out[] = [
                'key' => $key,
                'src' => $real ? self::versioned($real) : null,
                'webp' => $real ? self::variant($real, 'webp') : null,
                'srcset' => $real ? self::srcset($real, 'jpg') : null,
                'webpset' => $real ? self::srcset($real, 'webp') : null,
                'sizes' => self::sizes($tile['px']),
                'focal' => $focal,
                'kind' => $tile['kind'],
                'brief' => $tile['brief'],
                'isPlaceholder' => $isPlaceholder,
                'column' => $tile['column'],
                'row' => $tile['row'],
                'shape' => $tile['shape'],
                // Only the anchor is eager. Six eager images on a hero is a
                // request storm on a 4G connection.
                'eager' => $key === 'a',
            ];
        }

        return $out;
    }

    /** True while any tile is still showing a stand-in. */
    public static function awaitingRealPhotography(): bool
    {
        foreach (self::TILES as $tile) {
            if (self::resolve($tile['file']) === null) {
                return true;
            }
        }

        return false;
    }

    /** Tiles rendered at a given breakpoint, in manifest order. */
    public static function keysAt(string $breakpoint): array
    {
        return array_keys(array_filter(
            self::TILES,
            fn ($t) => $t['grid'][$breakpoint] !== null
        ));
    }

    /** The first existing file for a tile, or null. */
    private static function resolve(string $file): ?string
    {
        $path = "images/site/{$file}";

        return is_file(public_path($path)) ? $path : null;
    }


    /**
     * A tile spanning one column of four never needs a 1400px file. Sizes are
     * expressed against the real rendered box: the mosaic is half the hero on
     * desktop and full width below that.
     */
    private static function sizes(array $px): string
    {
        $tablet = $px['tablet'] ?: $px['desktop'];
        $mobile = $px['mobile'] ?: $tablet;

        return "(min-width: 1024px) {$px['desktop']}px, (min-width: 768px) {$tablet}px, {$mobile}px";
    }

    /** A derivative path, or null when it has not been generated. */
    private static function variant(string $path, string $ext, ?int $width = null): ?string
    {
        $base = preg_replace('/\.[a-z]+$/i', '', $path);
        $candidate = $width ? "{$base}-{$width}.{$ext}" : "{$base}.{$ext}";

        return is_file(public_path($candidate)) ? self::versioned($candidate) : null;
    }

    /**
     * Built only from derivatives that exist, so a missing generator run
     * degrades to a plain `src` rather than to a broken image.
     */
    private static function srcset(string $path, string $ext): ?string
    {
        $parts = [];

        foreach (self::WIDTHS as $w) {
            if ($v = self::variant($path, $ext, $w)) {
                $parts[] = asset($v)." {$w}w";
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * ⚠️ CACHE-BUSTING, AND IT IS NOT OPTIONAL — Hostinger's CDN holds images
     * for seven days, so a swapped photograph changes nothing a visitor sees
     * until the URL changes with it. That has already happened once.
     */
    private static function versioned(string $path): string
    {
        $full = public_path($path);

        return is_file($full) ? $path.'?v='.filemtime($full) : $path;
    }
}
