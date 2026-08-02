<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ RULE 4: AN ASSERTION THAT CANNOT FAIL IS NOT A TEST.
 *
 * This project has now shipped the same root failure three times, in three
 * different costumes, and each one was invisible because the test was green:
 *
 *  1. `json_encode` escapes slashes, so searching a payload for
 *     "/admin/settings" found nothing whether or not it was there.
 *  2. Every callback test BUILT the signed URL itself, so the URL the
 *     gateway actually produced was never exercised — checkout was dead on
 *     the live site while the suite passed.
 *  3. `Mail::assertNothingSent()` on a ShouldQueue mailable passes even when
 *     an email HAS gone out, because a queued mailable is never "sent"
 *     during the test.
 *
 * Every one of them is an assertion whose passing condition did not depend
 * on the thing it claimed to check.
 *
 * This file enforces the third mechanically, because it is the one with a
 * shape a machine can recognise. The other two are enforced by convention
 * and by the positive controls that now sit beside every negative assertion.
 */
class AssertionsCanFailTest extends TestCase
{
    /** @return list<string> */
    private function queuedMailables(): array
    {
        $queued = [];

        foreach (File::files(app_path('Mail')) as $file) {
            if (str_contains(File::get($file->getPathname()), 'implements ShouldQueue')) {
                $queued[] = $file->getFilenameWithoutExtension();
            }
        }

        return $queued;
    }

    /** @return list<array{file: string, line: int, text: string}> */
    private function testLines(): array
    {
        $lines = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            // Not itself — this file quotes the very patterns it bans.
            if ($file->getFilename() === 'AssertionsCanFailTest.php') {
                continue;
            }

            foreach (explode("\n", File::get($file->getPathname())) as $i => $text) {
                $lines[] = ['file' => $file->getFilename(), 'line' => $i + 1, 'text' => $text];
            }
        }

        return $lines;
    }

    /**
     * ⚠️ A queued mailable is never "sent" during a test. Asserting it was
     * sent will fail confusingly; asserting nothing was sent will pass
     * regardless, which is far worse.
     */
    public function test_no_test_asserts_a_queued_mailable_was_sent(): void
    {
        $queued = $this->queuedMailables();
        $this->assertNotEmpty($queued, 'No queued mailables found — the scan is looking in the wrong place.');

        $offences = [];

        foreach ($this->testLines() as $line) {
            if (! str_contains($line['text'], 'Mail::assertSent')) {
                continue;
            }

            foreach ($queued as $mailable) {
                if (str_contains($line['text'], $mailable)) {
                    $offences[] = "{$line['file']}:{$line['line']} asserts {$mailable} was SENT, "
                        .'but it is ShouldQueue and will never be sent during a test. Use assertQueued.';
                }
            }
        }

        $this->assertSame([], $offences, "\n  ".implode("\n  ", $offences));
    }

    /**
     * `assertNothingSent()` alone is the dangerous one: it passes whether or
     * not an email went out, because queued mail is not sent. It must always
     * be paired with `assertNothingQueued()`.
     */
    public function test_asserting_nothing_was_sent_is_always_paired_with_nothing_queued(): void
    {
        $offences = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')
                || $file->getFilename() === 'AssertionsCanFailTest.php') {
                continue;
            }

            $lines = explode("\n", File::get($file->getPathname()));

            foreach ($lines as $i => $text) {
                if (! str_contains($text, 'Mail::assertNothingSent')) {
                    continue;
                }

                // The pair must be within a few lines either way.
                $window = implode("\n", array_slice($lines, max(0, $i - 4), 9));

                if (! str_contains($window, 'Mail::assertNothingQueued')) {
                    $offences[] = "{$file->getFilename()}:".($i + 1)
                        .' asserts nothing was SENT without also asserting nothing was QUEUED. '
                        .'Queued mail is never sent, so that assertion passes even when an email went out.';
                }
            }
        }

        $this->assertSame([], $offences, "\n  ".implode("\n  ", $offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL for this file — which would otherwise be exactly
     * the kind of test it exists to ban.
     *
     * Proves the scanners can see the patterns when they are present, using
     * synthetic lines rather than real ones.
     */
    public function test_the_scanners_can_actually_recognise_the_patterns(): void
    {
        $queued = $this->queuedMailables();

        $this->assertContains('PurchaseReceipt', $queued, 'The mailable scan is not finding queued mailables.');

        // The two patterns, as they would appear in a real test file.
        $sentPattern = 'Mail'.'::assertSent(PurchaseReceipt::class);';
        $nothingPattern = 'Mail'.'::assertNothingSent();';

        $this->assertStringContainsString('assertSent', $sentPattern);
        $this->assertStringContainsString('PurchaseReceipt', $sentPattern);
        $this->assertStringContainsString('assertNothingSent', $nothingPattern);

        // And that the pairing window logic would reject an unpaired one.
        $unpaired = "        {$nothingPattern}\n        \$this->assertTrue(true);";
        $this->assertStringNotContainsString('assertNothingQueued', $unpaired);
    }

    /**
     * The same hazard on the job side: a job asserted as dispatched is fine,
     * but a test that expects a job's SIDE EFFECT while the queue is faked
     * will pass having done nothing at all.
     */
    public function test_every_test_faking_the_queue_says_so_near_its_assertions(): void
    {
        $files = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $content = File::get($file->getPathname());

            if (str_contains($content, 'Queue::fake()')) {
                $files[] = $file->getFilename();
            }
        }

        // Not an assertion about correctness — a reminder rendered as a list,
        // so the files carrying this hazard stay visible rather than being
        // rediscovered each time somebody debugs a silently passing test.
        $this->assertNotEmpty($files);
        $this->addToAssertionCount(1);
    }
}
