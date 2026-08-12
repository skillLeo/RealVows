<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A PATTERN THAT READS AS A RANGE AND IS ACTUALLY A SET.
 *
 * ── The bug this exists for ───────────────────────────────────────────────
 *
 * `MigrationReversibilityTest` counted its rollback steps with three globs:
 *
 *     2026_08_0[2-9]*      2026_0[9]*      202[7-9]*
 *
 * Read quickly that is "everything from 2 August onwards". It is not. The
 * first matches the 2nd to the 9th of August and then stops, because the `0`
 * is a literal and `[2-9]` only varies the second digit. Every migration dated
 * the 10th to the 31st was invisible.
 *
 * Nothing failed for months, because no migration was written after the 9th.
 * The first one that was made the test roll back a step too few and report a
 * failure that pointed at the migration rather than at the counting. The
 * existing "did this scan anything" guard could not help: the count was 13,
 * which is not zero and looks entirely reasonable.
 *
 * ⚠️ A WRONG NUMBER THAT SURVIVES A SANITY CHECK IS WORSE THAN NO NUMBER.
 *
 * ── Why this is a test and not the grep I ran once ────────────────────────
 *
 * Rule 5 covers one-off audits. I swept for this by hand and found only the
 * comment above quoting the old pattern — a clean result, delivered by a
 * command nobody can re-run and nobody will remember. The shape will reappear:
 * it is the natural way to write a date prefix, it looks correct, and it fails
 * silently at a boundary nobody tests. So the sweep is a guard now.
 */
class FalseRangePatternTest extends TestCase
{
    /**
     * ⚠️ A LITERAL DIGIT IMMEDIATELY FOLLOWED BY A BOUNDED CLASS.
     *
     * `0[2-9]`, `1[0-5]`, `20[1-2]`. The literal pins a decade and the class
     * varies only the unit, so the pattern covers one narrow band and silently
     * excludes everything above it. That is the whole family.
     *
     * A bare `[0-9]` is not matched and must not be: it is an ordinary,
     * correct, fully-covering digit class and flagging it would make this
     * guard noise on every phone-number validator in the codebase.
     */
    private const FALSE_RANGE = '/(?<![\[\\\\])\d\[\d-\d\]/';

    /**
     * Files where a date-shaped pattern would actually be selecting things.
     * Comments quoting the historical bug are excluded by stripping them.
     */
    private function scannedPaths(): array
    {
        $paths = [];

        foreach ([base_path('app'), base_path('tests'), base_path('database'),
            base_path('config'), base_path('routes'), base_path('tools')] as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            foreach (File::allFiles($dir) as $file) {
                if (! in_array($file->getExtension(), ['php', 'html', 'js'], true)) {
                    continue;
                }

                /*
                 * ⚠️ EXACTLY ONE EXCLUSION, AND IT IS THIS FILE.
                 *
                 * The positive control below plants the three globs that
                 * actually shipped, because a reader that cannot be shown
                 * catching the real bug is worth nothing. Those fixtures are
                 * the pattern, so the sweep finds them and fails on its own
                 * evidence.
                 *
                 * Excluded by exact path rather than by directory or by a
                 * substring, so it cannot quietly grow into "tests are not
                 * scanned" — which is where the bug lived in the first place.
                 */
                if ($file->getPathname() === __FILE__) {
                    continue;
                }

                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

    public function test_no_live_pattern_pins_a_digit_and_varies_only_the_unit(): void
    {
        $offences = [];

        $files = ScannedFiles::of(
            $this->scannedPaths(),
            'source files that could carry a date or version pattern',
            atLeast: 50,
        );

        foreach ($files as $path) {
            $source = $this->withoutComments(File::get($path));

            if (preg_match_all(self::FALSE_RANGE, $source, $matches)) {
                $relative = str_replace(base_path().'/', '', $path);

                foreach (array_unique($matches[0]) as $hit) {
                    $offences[] = "{$relative} contains [{$hit}]";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A pattern pins a literal digit and varies only the unit, which covers one narrow "
                ."band and silently excludes everything above it. `0[2-9]` matches the 2nd to "
                ."the 9th and nothing after. If a range is genuinely meant, compare values "
                ."rather than matching text:\n  ".implode("\n  ", $offences)
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), BOTH DIRECTIONS.
     *
     * The planted strings are the three globs that actually shipped. The clean
     * samples are the shapes that must NOT be flagged, because a reader that
     * fires on an ordinary `[0-9]` would be switched off within a day.
     */
    public function test_the_reader_can_see_the_pattern_that_shipped(): void
    {
        /*
         * ⚠️ THIS EXERCISES THE EXTRACTION, NOT JUST THE REGEX.
         *
         * The first version asserted the pattern matched a string. That proves
         * the regex compiles and nothing about the code path the sweep
         * actually runs, which is preg_match_all over comment-stripped source.
         * AssertionsCanFailTest caught it: a guard whose control does not run
         * the same extraction can have its extraction refactored to nothing
         * while the control keeps passing.
         */
        $plantedSource = "<?php \$files = glob('migrations/2026_08_0[2-9]*.php');\n"
            ."\$more = glob('migrations/202[7-9]*.php');\n";

        $found = preg_match_all(self::FALSE_RANGE, $this->withoutComments($plantedSource), $matches);

        $this->assertNotEmpty(
            $matches[0],
            'The sweep extracted nothing from source containing the exact globs that shipped.'
        );

        $this->assertSame(2, $found, 'The sweep found the wrong number of false ranges.');

        foreach ([
            "preg_match('/^[0-9]+$/', \$phone)",
            "'/[0-9]{4}-[0-9]{2}-[0-9]{2}/'",
            "basename(\$path) > '2026_08_01_999999'",
            "'/\\d{2,}/'",
        ] as $honest) {
            $this->assertDoesNotMatchRegularExpression(
                self::FALSE_RANGE,
                $honest,
                "The reader flags [{$honest}], which is correct code. An over-eager reader here "
                    .'would fire on every digit class in the codebase.'
            );
        }
    }

    /**
     * ⚠️ AND THE SCAN MUST HAVE SCANNED SOMETHING. The absent version of this
     * assertion is what let the zodiac grep print CLEAN over zero files.
     */
    public function test_the_sweep_reads_a_real_file_set(): void
    {
        $this->assertGreaterThan(
            50,
            count($this->scannedPaths()),
            'The sweep found almost no files, so a clean result means nothing.'
        );
    }

    /**
     * Comments are stripped so the docblock in MigrationReversibilityTest —
     * which quotes the original broken globs on purpose, as a warning — does
     * not fail the build for describing the bug it prevents.
     */
    private function withoutComments(string $source): string
    {
        $source = preg_replace('#/\*.*?\*/#s', ' ', $source) ?? $source;
        $source = preg_replace('#(?<!:)//[^\n]*#', ' ', $source) ?? $source;

        return preg_replace('/^\s*\*.*$/m', ' ', $source) ?? $source;
    }
}
