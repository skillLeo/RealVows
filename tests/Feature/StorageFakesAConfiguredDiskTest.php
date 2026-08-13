<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ A TEST MAY ONLY FAKE A DISK THE APPLICATION ACTUALLY USES.
 *
 * ── What went wrong, and it went wrong inside a positive control ──────────
 *
 * `Storage::fake('private')` does not fail when there is no `private` disk. It
 * INVENTS one, rooted at storage/framework/testing/disks/private, and hands it
 * back looking exactly like a working fake. Nothing warns. Nothing errors.
 *
 * The application stores photographs on disk `local`, whose root happens to be
 * storage/app/private — the similarity in the names is the whole trap. So the
 * test wrote its fixture into a disk nobody reads, the controller read the
 * REAL developer storage directory, and the two never met.
 *
 * ⚠️ THE TEST STILL PASSED, AND THAT IS THE SERIOUS PART. It passed because an
 * earlier run had left a five-byte file at exactly the path the fixture used,
 * in real storage, and every run since had been reading that leftover. Delete
 * one stray file and the test failed. Clone the repository onto a clean machine
 * and it failed there from the first run.
 *
 * The test resting on that leftover was `test_a_member_can_still_fetch_her_own`
 * — the POSITIVE CONTROL for the photograph rule. Its entire job is to prove
 * that the refusals around it mean "withheld" rather than "this never works for
 * anybody", and it was the assertion with nothing underneath it. A control that
 * is itself uncontrolled is worse than no control: it is the reason the
 * refusals were believed.
 *
 * ── ⚠️ WHY THIS IS A READER OVER SOURCE ───────────────────────────────────
 *
 * The fault cannot reproduce inside a normal run on this machine, because the
 * leftover file makes it pass. Any test that exercised the behaviour would be
 * subject to the same leftover. So the question is asked of the source instead:
 * every disk name handed to Storage::fake must appear in config/filesystems.php.
 */
class StorageFakesAConfiguredDiskTest extends TestCase
{
    public function test_no_test_fakes_a_disk_that_does_not_exist(): void
    {
        $configured = array_keys(config('filesystems.disks'));

        $offenders = [];
        $scanned = 0;
        $found = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            /*
             * ⚠️ THIS FILE IS THE ONE EXEMPTION, AND IT IS NOT A LOOPHOLE.
             *
             * The control below has to contain a known-bad disk name to prove
             * the reader still catches one, and the framework-behaviour test
             * has to fake an unconfigured disk to demonstrate that doing so
             * succeeds silently. Both are the evidence, not the offence.
             *
             * Named by basename rather than by a substring, so a future
             * `StorageFakesSomethingElseTest` does not inherit the exemption.
             */
            if ($file->getFilename() === basename(__FILE__)) {
                continue;
            }

            $scanned++;
            $source = File::get($file->getPathname());

            /*
             * ⚠️ COMMENTS ARE STRIPPED FIRST. Files that fixed this fault
             * explain it in their own docblocks, quoting the exact bad call —
             * RouteSmokeTest does — and a reader that cannot tell code from
             * prose reports the explanation as the offence. The explanation is
             * the most useful thing in those files and must not be a reason to
             * delete it.
             */
            $source = preg_replace('#/\*.*?\*/#s', '', $source);
            $source = preg_replace('#//.*$#m', '', (string) $source);

            // Only literal string arguments. A constant such as
            // PhotoStorageService::DISK is resolved by PHP and cannot drift
            // from the disk the application opens, which is the point of it.
            preg_match_all("/Storage::fake\(\s*'([^']+)'/", (string) $source, $matches);

            foreach ($matches[1] as $disk) {
                $found[] = $file->getRelativePathname()." → '{$disk}'";

                if (! in_array($disk, $configured, true)) {
                    $offenders[] = $file->getRelativePathname()." → '{$disk}'";
                }
            }
        }

        /*
         * ⚠️ THE EXTRACTION IS PROVED NON-EMPTY BEFORE ANYTHING IS CONCLUDED
         * FROM IT, AND ACROSS THE WHOLE SWEEP RATHER THAN PER FILE — most test
         * files fake nothing, so a per-file check would be asserting that every
         * test touches storage, which is a different and false claim.
         *
         * A pattern that has quietly stopped matching produces an empty
         * offender list, and an empty offender list is exactly what a clean
         * codebase produces. These two are indistinguishable without this line.
         */
        $this->assertNotEmpty($found, 'No Storage::fake call was found in any test, so the reader matched nothing.');

        /*
         * ⚠️ BOTH COUNTS ARE ASSERTED. A wrong base path scans nothing and
         * reports the cleanest possible result; a regex that has stopped
         * matching finds nothing and does the same. Neither is distinguishable
         * from a genuine pass without these two lines.
         */
        $this->assertGreaterThan(50, $scanned, 'Almost no test files were read.');

        $this->assertSame([], $offenders, implode("\n", [
            'These tests fake a disk that is not in config/filesystems.php.',
            'Storage::fake() invents the disk rather than failing, so the fixture is written',
            'somewhere the application never reads — and the test then passes or fails on',
            'whatever happens to be sitting in real storage.',
            '',
            'Configured disks: '.implode(', ', $configured),
        ]));
    }

    public function test_the_reader_can_still_see_a_disk_that_does_not_exist(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL. The assertion above is an empty-array check over
         * a regex, and a regex that quietly stops matching produces exactly the
         * same clean result as a codebase with nothing wrong in it.
         */
        $configured = array_keys(config('filesystems.disks'));

        $this->assertNotContains('private', $configured, implode("\n", [
            'A disk named `private` now exists in config/filesystems.php.',
            'That is fine, but this control used it as its known-bad example and must be',
            'given a different one, or it is asserting nothing.',
        ]));

        preg_match_all(
            "/Storage::fake\(\s*'([^']+)'/",
            "<?php Storage::fake('private'); Storage::fake('local');",
            $matches,
        );

        $this->assertSame(['private', 'local'], $matches[1], 'The reader no longer extracts disk names.');

        $bad = array_values(array_filter($matches[1], fn ($d) => ! in_array($d, $configured, true)));

        // It catches the invented one …
        $this->assertSame(['private'], $bad);

        // … and does not flag the real one, which a reader that matched
        // everything would also have reported.
        $this->assertContains('local', $configured);
    }

    /**
     * ⚠️ THE OTHER HALF: proof that faking an unconfigured disk really does
     * silently succeed, rather than this being a theory about why the test
     * passed. If a future Laravel makes it throw, this fails and the guard
     * above can be reconsidered rather than kept out of superstition.
     */
    public function test_faking_an_unconfigured_disk_silently_succeeds(): void
    {
        $this->assertNull(config('filesystems.disks.not_a_real_disk'));

        Storage::fake('not_a_real_disk');
        Storage::disk('not_a_real_disk')->put('a.txt', 'x');

        $this->assertTrue(Storage::disk('not_a_real_disk')->exists('a.txt'));
        $this->assertFalse(Storage::disk('local')->exists('a.txt'));
    }
}
