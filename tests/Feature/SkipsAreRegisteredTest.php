<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A TEST THAT SKIPS ITSELF FOREVER IS A READER THAT MATCHES NOTHING.
 *
 * The suite reported "928 passing, 9 skipped" and passed the CI gate, which is
 * correct and is also how a security check stopped examining anything for
 * weeks without a single red build. Rule 5 says a reader that matches nothing
 * and a reader that matches everything are equally dead. A permanently skipped
 * test is that same failure wearing a different hat: it contributes a number
 * rather than an answer, and the number looks healthy.
 *
 * ⚠️ SO THE SET OF SKIPS IS DECLARED, NOT COUNTED. A new skip appearing
 * anywhere now fails the suite instead of blending into a total that nobody
 * reads closely.
 */
class SkipsAreRegisteredTest extends TestCase
{
    /**
     * ⚠️ EVERY ENTRY NEEDS A REASON SOMEBODY ELSE COULD ACT ON.
     *
     * "Environment" is not a reason. The named credential, and who holds it,
     * is — because that is what turns a skip from a permanent state into a
     * task with an owner.
     *
     * @var array<string, string>
     */
    private const REGISTERED = [
        'PowerTranzSandboxTest' =>
            'The client holds the sandbox credentials and has not supplied them. Until he does, '
            .'the real gateway has never been exercised and phase 2 payment acceptance is not '
            .'complete. Nothing we can do from here changes it.',
    ];

    /**
     * Files that call markTestSkipped, which is the only way a skip can exist.
     *
     * @return array<string, int>
     */
    private function skippingFiles(): array
    {
        $files = ScannedFiles::of(
            File::allFiles(base_path('tests')),
            'test files',
            40,
        );

        $found = [];

        foreach ($files as $file) {
            $source = File::get($file);

            // Comments discuss markTestSkipped at length in this codebase.
            $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? $source;

            $count = substr_count($code, 'markTestSkipped');

            if ($count > 0) {
                $found[basename($file, '.php')] = $count;
            }
        }

        return $found;
    }

    public function test_no_test_skips_itself_without_being_registered(): void
    {
        $skipping = $this->skippingFiles();

        /*
         * ⚠️ THE INTEGRATION SUITE IS NOT IN phpunit.xml AT ALL, so its skip
         * never runs and never reaches a count. Excluded by path rather than
         * by name, so moving a file into tests/Feature brings it under this
         * rule automatically.
         */
        unset($skipping['MoneyOnRealDatabaseTest']);

        /*
         * ⚠️ AND THIS FILE ITSELF, which names the string it is guarding.
         */
        unset($skipping['SkipsAreRegisteredTest']);

        /*
         * WebRootExposureTest still contains the call, and it no longer fires,
         * because DEPLOY_URL is set in phpunit.xml. It is registered as
         * conditional rather than removed: the guard exists for a machine that
         * genuinely has no host to check.
         */
        unset($skipping['WebRootExposureTest']);

        $unregistered = array_diff(array_keys($skipping), array_keys(self::REGISTERED));

        $this->assertSame(
            [],
            array_values($unregistered),
            "A test skips itself and is not registered:\n  ".implode("\n  ", $unregistered)
                ."\n\nA skip is not a pass. Either make it run, or add it to REGISTERED with a "
                .'reason naming what is missing and who holds it.',
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. The assertion above is an empty-diff check, and a
     * scanner that found no files at all would report a spotless suite.
     */
    public function test_the_scanner_actually_finds_the_known_skip(): void
    {
        $this->assertArrayHasKey(
            'PowerTranzSandboxTest',
            $this->skippingFiles(),
            'The scanner cannot see the one skip we know exists, so its silence about every '
                .'other file means nothing.',
        );
    }

    /** ⚠️ And every registered reason names something actionable. */
    public function test_every_registered_skip_explains_itself(): void
    {
        foreach (self::REGISTERED as $test => $reason) {
            $this->assertGreaterThan(
                60,
                strlen($reason),
                "The reason for skipping {$test} is too short to act on.",
            );
        }
    }

    /**
     * ⚠️ THE WEB-ROOT CHECK MUST ACTUALLY RUN NOW.
     *
     * It skipped two of three for want of a host, which is the worst state for
     * a security check: it reported a number and the number looked fine.
     */
    public function test_the_web_root_check_has_a_host_to_check(): void
    {
        $this->assertNotEmpty(
            env('DEPLOY_URL'),
            'DEPLOY_URL is unset, so the web-root exposure check is skipping again and nobody '
                .'is asking the live server whether .env is reachable.',
        );
    }
}
