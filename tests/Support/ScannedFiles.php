<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Finder\SplFileInfo;

/**
 * ⚠️ A GUARD THAT SCANS AN EMPTY SET REPORTS CLEAN.
 *
 * This exists because of the worst instance of this project's recurring
 * failure. The zodiac proof — the single constraint that, if it fails, hands
 * the client a product with the matching mechanism visible on screen — was run
 * as a shell grep with an unquoted variable. The shell treated the whole file
 * list as one path, grep found no such file, and the proof printed CLEAN.
 *
 * It found nothing because it looked at nothing, and there is no way to tell
 * that outcome from a genuine pass by reading the output.
 *
 * Every file-scanning guard in this suite now takes its file set through here,
 * which fails loudly on an empty set instead of quietly agreeing with itself.
 * PositiveControlsAreAliveTest fails the build if a guard enumerates files
 * without doing so.
 */
class ScannedFiles
{
    /**
     * @param  iterable<SplFileInfo|string>  $files
     * @param  string  $what  Named in the failure, so an empty scan is legible
     * @return list<string> Absolute paths
     */
    public static function of(iterable $files, string $what, int $atLeast = 1): array
    {
        $paths = [];

        foreach ($files as $file) {
            $paths[] = $file instanceof SplFileInfo ? $file->getPathname() : (string) $file;
        }

        Assert::assertGreaterThanOrEqual(
            $atLeast,
            count($paths),
            "The scan of {$what} found ".count($paths).' files, expected at least '.$atLeast.". "
                ."A guard that scans nothing reports clean, which is indistinguishable from a "
                ."genuine pass. Either the path is wrong or the files have moved."
        );

        return $paths;
    }

    /**
     * The same, filtered by extension.
     *
     * @param  iterable<SplFileInfo>  $files
     * @param  list<string>  $extensions
     * @return list<string>
     */
    public static function withExtension(iterable $files, array $extensions, string $what, int $atLeast = 1): array
    {
        $paths = [];

        foreach ($files as $file) {
            $path = $file instanceof SplFileInfo ? $file->getPathname() : (string) $file;

            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                $paths[] = $path;
            }
        }

        return self::of($paths, $what, $atLeast);
    }
}
