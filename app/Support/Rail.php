<?php

namespace App\Support;

/**
 * ⚠️ THE MARQUEE UNDER "EVERY INTRODUCTION WE MAKE IS ONE WE WOULD STAND
 * BEHIND" — twelve photographs sliding continuously, like a headline ticker.
 *
 * ── Why this works now and did not before ─────────────────────────────────
 *
 * This section used to hold exactly two photographs, and the CSS carried a
 * standing note forbidding a rail animation: "a loop of two images either
 * repeats visibly or leaves gaps". That was correct. A seamless marquee
 * translates the track by exactly half its width, which means the track must
 * hold TWO full copies of the set — so the set itself has to be wide enough
 * to fill the viewport on its own, or the viewer watches the same face come
 * round every few seconds and the whole thing reads as a gif.
 *
 * Twelve tiles at roughly 180px is about 2300px of track. That covers a
 * 1440px viewport with room to spare, so the repeat never lands on screen.
 * The constraint was never "no motion" — it was "not enough photographs".
 *
 * ── The files are pre-cropped, deliberately ───────────────────────────────
 *
 * Every rail image is generated at exactly 560x747, which is the 3:4 the
 * tiles render at. The crop therefore happens once, in the generator, rather
 * than twelve times in the browser against twelve different source shapes.
 * The focal point below is a safety net for that — it is not a per-image
 * judgement the way the hero's are, because there is nothing left to crop.
 */
class Rail
{
    /** The tile ratio, and the ratio the files are generated at. */
    public const RATIO = '3 / 4';

    /**
     * @var list<array{file: string, photographer: ?string, source: ?string}>
     */
    public const IMAGES = [
        ['file' => 'rail-01.jpg', 'photographer' => 'cottonbro studio', 'source' => 'https://www.pexels.com/photo/6284651/'],
        ['file' => 'rail-02.jpg', 'photographer' => 'cottonbro studio', 'source' => 'https://www.pexels.com/photo/6276720/'],
        ['file' => 'rail-03.jpg', 'photographer' => 'Gustavo Fring', 'source' => 'https://www.pexels.com/photo/7155850/'],
        ['file' => 'rail-04.jpg', 'photographer' => 'Dee Visuals', 'source' => 'https://www.pexels.com/photo/34205708/'],
        ['file' => 'rail-05.jpg', 'photographer' => 'Ron Lach', 'source' => 'https://www.pexels.com/photo/9652793/'],
        ['file' => 'rail-06.jpg', 'photographer' => 'Jetro Castro', 'source' => 'https://www.pexels.com/photo/9857248/'],
        ['file' => 'rail-07.jpg', 'photographer' => 'Thirdman', 'source' => 'https://www.pexels.com/photo/8487858/'],
        ['file' => 'rail-08.jpg', 'photographer' => 'Justin Thompson', 'source' => 'https://www.pexels.com/photo/27979405/'],
        // ⚠️ Supplied directly under a hashed filename. Almost certainly
        // Pexels, but "almost certainly" is not a licence record.
        ['file' => 'rail-09.jpg', 'photographer' => null, 'source' => null],
        ['file' => 'rail-10.jpg', 'photographer' => 'Sema Nur', 'source' => 'https://www.pexels.com/photo/15318828/'],
        ['file' => 'rail-11.jpg', 'photographer' => 'Amine Ispir', 'source' => 'https://www.pexels.com/photo/14588784/'],
        ['file' => 'rail-12.jpg', 'photographer' => 'Mahmoud Elbakstani', 'source' => 'https://www.pexels.com/photo/31619517/'],
    ];

    /**
     * ⚠️ A SEAMLESS MARQUEE NEEDS AT LEAST THIS MANY.
     *
     * Below it the loop point lands inside the viewport and the repeat
     * becomes visible, which is the failure the old two-image version was
     * protected from by forbidding motion outright.
     */
    public const MINIMUM_FOR_A_SEAMLESS_LOOP = 10;

    /**
     * @return list<array{src: string, webp: ?string}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::IMAGES as $image) {
            $path = "images/site/{$image['file']}";

            if (! is_file(public_path($path))) {
                continue;
            }

            $webp = preg_replace('/\.jpg$/', '.webp', $path);

            $out[] = [
                'src' => self::versioned($path),
                'webp' => is_file(public_path($webp)) ? self::versioned($webp) : null,
            ];
        }

        return $out;
    }

    /** True while there are too few photographs for the loop to hide itself. */
    public static function tooFewToScroll(): bool
    {
        return count(self::all()) < self::MINIMUM_FOR_A_SEAMLESS_LOOP;
    }

    /** Hostinger's CDN holds images for seven days; a swap needs a new URL. */
    private static function versioned(string $path): string
    {
        $full = public_path($path);

        return is_file($full) ? $path.'?v='.filemtime($full) : $path;
    }
}
