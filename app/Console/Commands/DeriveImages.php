<?php

namespace App\Console\Commands;

use App\Support\Mosaic;
use Illuminate\Console\Command;

/**
 * ⚠️ A 160px TILE MUST NOT DOWNLOAD A 1400px PHOTOGRAPH.
 *
 * Seven images on the hero is a real budget question on Caribbean 4G, which
 * is the connection this product's members are actually on. Without
 * derivatives the browser fetches one large file per tile and the hero costs
 * several megabytes for boxes that are 160px wide.
 *
 * Run after any photograph changes:
 *
 *     php artisan images:derive
 *
 * ⚠️ It writes only what is missing, so it is safe to run repeatedly, and it
 * is deliberately NOT wired into the deploy — generating images on a shared
 * host during a deploy is how a deploy times out. Run it locally and ship the
 * output.
 */
class DeriveImages extends Command
{
    protected $signature = 'images:derive {--force : Rebuild derivatives that already exist}';

    protected $description = 'Generate WebP and JPEG derivatives for every mosaic photograph';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD is not available, so no derivatives can be generated.');

            return self::FAILURE;
        }

        $made = 0;
        $skipped = 0;
        $missing = [];

        foreach ($this->sourceFiles() as $file) {
            $path = public_path("images/site/{$file}");

            if (! is_file($path)) {
                $missing[] = $file;

                continue;
            }

            [$w, $h] = getimagesize($path);

            foreach (Mosaic::WIDTHS as $width) {
                /*
                 * ⚠️ Never upscale. A 320px source blown up to 1400px is a
                 * bigger file that looks worse, and the browser would happily
                 * pick it because the descriptor claims it is sharper.
                 */
                if ($width > $w) {
                    continue;
                }

                foreach (['webp', 'jpg'] as $ext) {
                    $out = public_path(
                        'images/site/'.pathinfo($file, PATHINFO_FILENAME)."-{$width}.{$ext}"
                    );

                    if (is_file($out) && ! $this->option('force')) {
                        $skipped++;

                        continue;
                    }

                    $this->resize($path, $out, $width, (int) round($h * $width / $w), $ext);
                    $made++;
                }
            }
        }

        $this->info("Derivatives written: {$made}, already present: {$skipped}");

        if ($missing !== []) {
            // Loud, not silent — a missing source is the normal state while
            // photography is being sourced, but it must never be invisible.
            $this->warn('No source file yet for: '.implode(', ', $missing));
        }

        $this->reportWeight();

        return self::SUCCESS;
    }

    /** Every distinct file the mosaic and the couples pair can render. */
    private function sourceFiles(): array
    {
        $files = array_column(Mosaic::TILES, 'file');

        foreach (['couple-muslim.jpg', 'couple-christian.jpg'] as $standIn) {
            $files[] = $standIn;
        }

        return array_values(array_unique($files));
    }

    private function resize(string $src, string $dest, int $w, int $h, string $ext): void
    {
        $image = imagecreatefromjpeg($src);
        $canvas = imagecreatetruecolor($w, $h);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image));

        $ext === 'webp'
            ? imagewebp($canvas, $dest, 78)
            : imagejpeg($canvas, $dest, 82);

        imagedestroy($canvas);
        imagedestroy($image);
    }

    /**
     * ⚠️ REPORT THE NUMBER RATHER THAN CLAIMING THE BUDGET IS MET.
     *
     * The mobile budget is 250KB for the whole hero. This measures what a
     * phone would actually fetch: the five tiles it renders, at the widest
     * derivative it would pick for a 2x screen.
     */
    private function reportWeight(): void
    {
        $total = 0;
        $counted = 0;

        foreach (Mosaic::tiles() as $tile) {
            if (! in_array($tile['key'], Mosaic::keysAt('mobile'), true)) {
                continue;
            }

            if (! $tile['src']) {
                continue;
            }

            $base = public_path(preg_replace('/\?v=\d+$/', '', $tile['src']));
            $stem = preg_replace('/\.[a-z]+$/i', '', $base);

            // A 1-column mobile tile is ~160 CSS px, so 320w at 2x.
            foreach ([320, 640] as $w) {
                $candidate = "{$stem}-{$w}.webp";

                if (is_file($candidate)) {
                    $total += filesize($candidate);
                    $counted++;

                    break;
                }
            }
        }

        $kb = round($total / 1024);
        $line = "Mobile hero weight: {$kb}KB across {$counted} tiles (budget 250KB)";

        $kb > 250 ? $this->warn($line.' — OVER') : $this->info($line);
    }
}
