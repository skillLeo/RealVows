<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A HAPTIC IS THE PRODUCT TOUCHING SOMEBODY.
 *
 * It should happen when something has actually happened to them. Three times in
 * a whole membership is the right order of magnitude: the unseal, a trusted
 * contact clearing an introduction, and mutual interest. All three are things a
 * member would tell somebody about.
 *
 * ── What was actually shipped ─────────────────────────────────────────────
 *
 * Four call sites, none of them on that list. The worst fired on
 * `router.on('start')` — a buzz on EVERY page navigation, which is precisely
 * the "constant haptics feel cheap" failure the brief warns about, live. The
 * others were the pull-to-refresh threshold and a long-press, both gestures
 * rather than events.
 *
 * ⚠️ SO THIS IS A CLOSED LIST, NOT A GUIDELINE. "Use haptics sparingly" is
 * advice, and advice loses to whoever is adding a nice touch on a Friday. A
 * fourth call site anywhere outside haptics.js fails the build.
 */
class HapticsTest extends TestCase
{
    private const OWNER = 'resources/js/haptics.js';

    public function test_only_the_haptics_module_may_call_vibrate(): void
    {
        $offenders = [];

        $files = ScannedFiles::withExtension(
            File::allFiles(resource_path('js')),
            ['js', 'vue'],
            'front-end sources',
            50,
        );

        foreach ($files as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if ($relative === self::OWNER) {
                continue;
            }

            $source = File::get($path);

            // Comments referring to the rule are not call sites.
            $code = preg_replace(['#/\*.*?\*/#s', '#(?<!:)//[^\n]*#', '#\{\{--.*?--\}\}#s'], ' ', $source);

            if (str_contains((string) $code, 'navigator.vibrate')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "navigator.vibrate is called outside resources/js/haptics.js. Haptics fire at three "
                ."moments and the list is closed — a buzz on navigation or on a gesture is the "
                ."thing that makes a product feel cheap:\n  ".implode("\n  ", $offenders)
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. The assertion above is "no file contains a string",
     * which also passes when the scan reads nothing at all.
     */
    public function test_the_scan_reads_real_files_and_can_see_a_call(): void
    {
        $files = ScannedFiles::withExtension(
            File::allFiles(resource_path('js')),
            ['js', 'vue'],
            'front-end sources',
            50,
        );

        $this->assertGreaterThan(50, count($files));

        // The owner genuinely contains what the others must not.
        $this->assertStringContainsString(
            'navigator.vibrate',
            File::get(base_path(self::OWNER)),
            'The haptics module does not call vibrate, so the exclusion above is guarding nothing.'
        );
    }

    /** The three moments are named, and there are exactly three. */
    public function test_exactly_three_moments_are_defined(): void
    {
        $source = File::get(base_path(self::OWNER));

        foreach (['unseal', 'guardianApproved', 'mutualInterest'] as $moment) {
            $this->assertStringContainsString($moment, $source, "[{$moment}] is not defined.");
        }

        preg_match('/const MOMENTS = \{(.*?)\};/s', $source, $matches);

        $this->assertCount(
            3,
            array_filter(array_map('trim', explode("\n", $matches[1] ?? '')), fn ($l) => str_contains($l, ':')),
            'The number of haptic moments has changed. Three is a decision, not a coincidence.'
        );
    }

    /** ⚠️ Silent under reduced motion. A haptic is motion somebody can feel. */
    public function test_it_is_silent_under_reduced_motion(): void
    {
        $this->assertStringContainsString(
            'prefers-reduced-motion',
            File::get(base_path(self::OWNER)),
            'Haptics fire regardless of the reduced-motion preference. A vibration is motion.'
        );
    }
}
