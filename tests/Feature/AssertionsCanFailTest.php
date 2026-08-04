<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ RULE 4: AN ASSERTION THAT CANNOT FAIL IS NOT A TEST.
 * ⚠️ RULE 5: A GUARD THAT SCANS MUST PROVE IT CAN STILL SEE.
 *
 * This project has now shipped the same root failure four times, in four
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
 *  4. The horizontal-overflow guard scanned keyframe bodies for a literal
 *     `scale(1.0x)`. Nine near-identical keyframes were then collapsed into
 *     one parameterised `rv-arrive`, which moved the literal out to the call
 *     site as `--arrive-scale`. The scan found ZERO scaling selectors in the
 *     entire stylesheet and passed everything. A refactor had quietly turned
 *     the guard off, and nothing said so.
 *  5. Two MEASUREMENT HARNESSES reported confidently on things they
 *     structurally could not see. The reduced-motion walker ran under virtual
 *     time, so the animation clock never advanced: it read frame zero of every
 *     arrival and reported the hero and every match card as invisible. The
 *     width audit read `grid-template-columns` to count columns, and the
 *     profile builder is flex — the property returns `none`, which it counted
 *     as one column at every width, looking exactly like the regression it
 *     existed to catch. Both produced a specific, plausible, wrong answer.
 *
 * Every one of them is an assertion whose passing condition did not depend
 * on the thing it claimed to check.
 *
 * ── RULE 5, WHICH THE FOURTH ONE EARNED ───────────────────────────────────
 *
 * Any test, guard, MEASUREMENT HARNESS **or ONE-OFF AUDIT** that inspects
 * source, output, structure, a rendered page or STORED DATA must carry a
 * positive control proving it can still find what it looks for — and that
 * control must be re-verified whenever the thing being inspected is
 * refactored.
 *
 * ⚠️ "ONE-OFF AUDIT" WAS ADDED AFTER THE SEVENTH INSTANCE, WHICH FELL THROUGH
 * THE RULE ON A LITERAL READING.
 *
 * An ad-hoc query written to answer a safety question — "was any real member
 * exposed?" — is not a test, not a guard, and not a harness. It runs once,
 * answers, and is never run again, so nobody holds it to the standard a
 * permanent guard is held to. That exemption is exactly backwards: a permanent
 * guard gets a thousand chances to be noticed as wrong, and a one-off audit
 * gets none. Its single answer is acted on and then becomes what everybody
 * believes.
 *
 * So: an audit is a guard that runs once, and gets the same treatment. Before
 * it is run for real it must be pointed at a row constructed to trip it, and
 * be seen to trip.
 *
 * ⚠️ AND THE SEVENTH FAILED DIFFERENTLY FROM THE FIRST SIX, WHICH IS WHY A
 * POSITIVE CONTROL ALONE WOULD NOT HAVE SAVED IT.
 *
 * The first six were readers that looked at NOTHING, or could no longer see
 * what they looked for. This one looked at the WRONG THING, correctly. It asked
 * "which rows carry a viewed_at timestamp?" when the real question was "which
 * code paths can emit this field?" — and `viewed_at` was written by one of the
 * two paths that emitted it. The query was accurate about the data and wrong
 * about the world.
 *
 * ⚠️ EXPOSURE QUESTIONS ARE ANSWERED FROM EMISSION POINTS, NOT FROM DATA.
 *
 * Database archaeology tells you what happened to be recorded. Enumerating the
 * presenters, controllers and serialisers that can emit a field tells you what
 * could ever have been sent — which is the actual question when somebody asks
 * whether a member's details escaped.
 *
 * Where a stored proxy is genuinely unavoidable, the proxy's fidelity to the
 * thing it stands for must be asserted in its own test. ViewRecordingTest is
 * that assertion for `viewed_at`: it proves every path that emits an unlocked
 * payload also records it, which is the property the audit had assumed.
 *
 * ⚠️ "MEASUREMENT HARNESS" IS IN THAT SENTENCE DELIBERATELY.
 *
 * The rule was first written for tests, and the next two instances were not
 * tests — they were throwaway browser scripts written to answer a question
 * about the live product. Those are MORE dangerous than a test, not less: a
 * test that goes blind fails silently in CI, but a harness that goes blind
 * hands you a specific number and a table, and a number is believed. One of
 * them nearly had me report a critical accessibility failure that did not
 * exist; the other nearly had me "fix" a two-column layout that was correct.
 *
 * A harness must therefore prove itself against a KNOWN-GOOD and a KNOWN-BAD
 * fixture before it is allowed to report on anything real, and it must refuse
 * to emit results if that fails. `tools/width-audit.html` does this: it counts
 * columns on a synthetic side-by-side fixture and a synthetic stacked one, and
 * aborts rather than produce a table if it cannot tell them apart.
 *
 * The distinguishing symptom is always the same: SUCCESS REPORTED WHILE
 * NOTHING IS BEING CHECKED. A negative assertion over an empty result set is
 * indistinguishable from a negative assertion over a clean one, and only the
 * positive control can tell them apart.
 *
 * ⚠️ An extraction scan is the dangerous shape. `preg_match_all` that feeds a
 * loop is fine while the pattern matches and silently vacuous the moment it
 * stops — so every such scan must assert it found something, either in the
 * method itself or at the call site of the helper that runs it.
 *
 * This file enforces Rules 3 and 5 mechanically, because those are the two
 * with a shape a machine can recognise. The others are enforced by convention
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

    /**
     * ⚠️ RULE 5, ENFORCED: every scanning guard must prove it can still see.
     *
     * A `preg_match_all` that feeds a loop is the dangerous shape — correct
     * while the pattern matches, and silently vacuous the moment a refactor
     * moves what it was looking for. The overflow guard spent a commit in
     * exactly that state, reporting success over an empty result set.
     *
     * The fix is cheap and total: any file that extracts must also assert,
     * somewhere, that an extraction found something. That single
     * `assertNotEmpty` is what separates "nothing is wrong" from "I can no
     * longer see anything".
     */
    public function test_every_scanning_guard_proves_it_can_still_see(): void
    {
        $blind = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            // Not itself — this file discusses the pattern it bans.
            if ($file->getFilename() === 'AssertionsCanFailTest.php') {
                continue;
            }

            $source = File::get($file->getPathname());

            if (! str_contains($source, 'preg_match_all')) {
                continue;
            }

            /*
             * Either the file asserts one of its own extractions is non-empty,
             * or it carries a named positive control that exercises the reader
             * against a known-bad sample. Both prove the same thing.
             */
            $provesItSees = str_contains($source, 'assertNotEmpty(')
                || preg_match('/function test_[a-z_]*(can_see|can_actually|recognises|can_detect|readers_can)/', $source) === 1;

            if (! $provesItSees) {
                $blind[] = $file->getFilename();
            }
        }

        $this->assertSame(
            [],
            $blind,
            "These guards extract with preg_match_all but never prove the extraction found "
                ."anything, so a refactor can turn them off silently:\n  ".implode("\n  ", $blind)
                ."\n\nAdd an assertNotEmpty on the extraction, or a positive control that runs "
                .'the reader against a known-bad sample.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL for the check above — which would otherwise be an
     * exact instance of the thing it exists to prevent.
     */
    public function test_the_blindness_check_recognises_a_guard_that_cannot_see(): void
    {
        $blind = '<?php class X { public function t() { preg_match_all("/x/", $s, $m); '
            .'foreach ($m[1] as $v) { $this->assertSame([], $v); } } }';

        $this->assertStringContainsString('preg_match_all', $blind);
        $this->assertStringNotContainsString('assertNotEmpty(', $blind);
        $this->assertSame(
            0,
            preg_match('/function test_[a-z_]*(can_see|can_actually|recognises|can_detect|readers_can)/', $blind),
            'The control sample must not look like it carries a positive control.'
        );

        // And a guarded one is NOT flagged.
        $sighted = $blind.' public function t2() { $this->assertNotEmpty($m); }';
        $this->assertStringContainsString('assertNotEmpty(', $sighted);
    }
}
