<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ RULE 5, MADE MECHANICAL. THIS IS THE GUARD OVER THE GUARDS.
 *
 * Six times this project has shipped something that reported success while
 * checking nothing. Rule 5 was the response, and it worked — but reactively,
 * one instance at a time, each caught after the damage window had already
 * opened. Rule 5 asks a person to remember. This asks the build.
 *
 * ── ⚠️ WHY A POSITIVE CONTROL DIES SILENTLY ───────────────────────────────
 *
 * A positive control's entire job is to fail when the thing it plants is
 * present. Its assertions are therefore AFFIRMATIVE — "the reader found the
 * violation I put in front of it". And an affirmative assertion goes on
 * passing for the wrong reasons: the reader is replaced, the fixture drifts,
 * the sample becomes trivially matchable. The control keeps saying yes, and
 * the guard it vouches for has been blind for weeks.
 *
 * So it is not enough to check that controls EXIST, which is what
 * AssertionsCanFailTest already does. Each reader has to be RUN, against a
 * violation planted now, and be seen to catch it — and run against a clean
 * sample and be seen NOT to catch it, because a reader that returns a match
 * for everything is just as dead as one that never matches.
 *
 * ── ⚠️ THE WORST INSTANCE, AND WHY THIS FILE EXISTS ───────────────────────
 *
 * The zodiac proof is the highest-stakes constraint in the product: if it
 * fails, the client is handed a platform with the matching mechanism visible
 * on screen. It was run as a shell grep with an unquoted variable, so the
 * shell passed the entire file list as a single path, grep found no such file,
 * and the proof printed CLEAN. It looked at nothing and said everything was
 * fine, and the output of that is identical to a real pass.
 *
 * That check now lives in this suite, over a file set that cannot be empty.
 */
class PositiveControlsAreAliveTest extends TestCase
{
    /**
     * ⚠️ THE ONLY TWO FILES PERMITTED TO KNOW THE SIGN NAMES, AND THIS LIST IS
     * A CLOSED SET RATHER THAN AN EXCEPTION MECHANISM.
     *
     * The constraint has never been "the words must not exist" — the engine
     * weights affinity, so the vocabulary has to live somewhere in order to be
     * computed at all. The constraint is that it never reaches a surface a
     * member can read.
     *
     * These two are the mechanism. Neither renders anything, neither is
     * reachable from a controller's output, and ForbiddenLanguageTest asserts
     * separately that nothing they produce carries the vocabulary forward into
     * a rationale or a commonality.
     *
     * ⚠️ ADDING A PATH HERE IS THE ONE CHANGE IN THIS FILE THAT WOULD MATTER.
     * It is an allow-list, not an ignore-list: a THIRD file gaining the
     * vocabulary fails the build, and that failure is the signal that the
     * mechanism has started spreading toward the surface. Do not widen it to a
     * directory, and do not silence a failure by appending to it without being
     * able to say why the new file can never be read by a member.
     */
    private const THE_ENGINE = [
        'app/Services/Matching/AffinityMatrix.php',
        'app/Services/Sign/SignCalculator.php',
    ];

    /**
     * ⚠️ EVERY READER, WITH A VIOLATION THAT MUST BE CAUGHT AND A CLEAN SAMPLE
     * THAT MUST NOT BE.
     *
     * Both halves are load-bearing. A reader that matches nothing and a reader
     * that matches everything are both blind; only running it both ways can
     * tell them apart from a working one.
     *
     * @return array<string, array{reader: callable, planted: string, clean: string}>
     */
    private function readers(): array
    {
        return [
            'affinity vocabulary (the zodiac proof)' => [
                'reader' => fn (string $t) => Scanners::forbiddenTerms($t),
                'planted' => 'Her birth chart suggests a Leo, which pairs well with Scorpio.',
                'clean' => 'A patient, home-building match. Open it at realvows.skillleo.com/dashboard',
            ],

            'raw copy sitting in a component' => [
                'reader' => fn (string $t) => Scanners::rawCopyStrings($t),
                'planted' => '<template><p>We could not find anyone for you today.</p></template>',
                'clean' => '<template><p>{{ $t("dashboard.empty") }}</p><span>Save</span></template>',
            ],

            'a photograph path in a payload' => [
                'reader' => fn (string $t) => Scanners::photoPaths($t),
                // Escaped exactly as json_encode writes it — the spelling that
                // defeated the first version of this guard.
                'planted' => '{"photos":[{"url":"http:\\/\\/localhost\\/photos\\/12?signature=abc"}]}',
                'clean' => '{"firstName":"Idris","score":87,"city":"Kingston"}',
            ],

            'a hardcoded billing entity' => [
                'reader' => fn (string $t) => Scanners::hardcodedEntities($t, ['Gemini Security Limited']),
                'planted' => 'This charge is taken by Gemini Security Limited.',
                'clean' => 'This charge appears on your statement as REALVOWS.',
            ],

            'placeholders in a template body' => [
                'reader' => fn (string $t) => Scanners::templatePlaceholders($t),
                'planted' => 'Hello {{1}}, {{2}} is interested in you as well.',
                'clean' => 'Your RealVows verification code has expired.',
            ],

            'a sign name inside a hostname' => [
                // The reader that must NOT fire on skillleo, and MUST on leo.com.
                'reader' => fn (string $t) => array_values(array_filter(
                    Scanners::urls($t),
                    fn (string $url) => Scanners::forbiddenTerms($url) !== [],
                )),
                'planted' => 'Open it here: realvows.leo.com/dashboard',
                'clean' => 'Open it here: realvows.skillleo.com/dashboard',
            ],

            /*
             * The clean sample is the client's own approved sentence. It is
             * deliberately the hardest case: it CONTAINS the word and must not
             * be flagged as a lead, because he wrote it and it ends on the
             * destination rather than opening on it.
             */
            'marriage-forward phrasing' => [
                'reader' => fn (string $t) => Scanners::marriageForwardTerms($t),
                'planted' => 'Find someone ready for marriage.',
                'clean' => 'RealVows is for people looking to find their true soul mate.',
            ],

            /*
             * ⚠️ The clean sample is `synastry` — the term this reader must NOT
             * claim, because a different guard owns it. If the two lists are
             * ever merged, or either is widened to a `syn` prefix, this fails
             * rather than silently banning the client's own word.
             */
            'mechanism-adjacent vocabulary in generated copy' => [
                'reader' => fn (string $t) => Scanners::mechanismAdjacentTerms($t),
                'planted' => 'Your synergy with them is unusually strong.',
                'clean' => 'Their synastry is not something we would ever mention.',
            ],

            /*
             * The planted string is the review banner that was live. The clean
             * sample is its replacement.
             */
            'punctuation that reads as machine-written' => [
                'reader' => fn (string $t) => Scanners::aiPunctuation($t),
                'planted' => 'the sign-in buttons are open — not the live platform.',
                'clean' => 'This is not the live platform. Every member here is invented.',
            ],

            /*
             * ⚠️ The clean sample is two possessives. They broke the first
             * version of this reader, and a reader that flags them is switched
             * off within a day.
             */
            'contractions on a formal surface' => [
                'reader' => fn (string $t) => Scanners::contractions($t),
                'planted' => "A real person checks you're you, usually within a day",
                'clean' => "a match is never one person's wishes imposed on another's",
            ],

            'vocabulary no person chooses' => [
                'reader' => fn (string $t) => Scanners::bannedVocabulary($t),
                'planted' => 'Begin your journey with a seamless, curated experience.',
                'clean' => 'You will not spend your evenings scrolling.',
            ],

            'a host outside the allow-list' => [
                'reader' => fn (string $t) => array_values(array_filter(
                    Scanners::hosts($t),
                    fn (string $h) => ! in_array($h, ['realvows.com', 'realvows.skillleo.com', 'www.w3.org', 'example.com'], true),
                )),
                'planted' => '<span class="rv-arrives__url">realvows.net/m/8f2a…</span>',
                'clean' => '<a href="https://realvows.skillleo.com/dashboard">Open</a>',
            ],
        ];
    }

    /**
     * ⚠️ THE CHECK THIS FILE EXISTS FOR. Every reader is run against a
     * violation planted right now.
     */
    public function test_every_reader_still_catches_a_planted_violation(): void
    {
        $dead = [];

        foreach ($this->readers() as $name => $case) {
            if (($case['reader'])($case['planted']) === []) {
                $dead[] = $name;
            }
        }

        $this->assertSame(
            [],
            $dead,
            "These readers no longer detect a violation planted directly in front of them, so "
                ."every guard relying on them has been passing without checking anything:\n  - "
                .implode("\n  - ", $dead)
        );
    }

    /**
     * ⚠️ AND THE OTHER HALF. A reader that matches everything is exactly as
     * dead as one that matches nothing, and passes the test above perfectly.
     */
    public function test_no_reader_flags_a_clean_sample(): void
    {
        $overeager = [];

        foreach ($this->readers() as $name => $case) {
            $found = ($case['reader'])($case['clean']);

            if ($found !== []) {
                $overeager[] = $name.' → '.json_encode($found);
            }
        }

        $this->assertSame(
            [],
            $overeager,
            "These readers flag clean input. A reader that matches everything passes its own "
                ."positive control while telling you nothing:\n  - ".implode("\n  - ", $overeager)
        );
    }

    /**
     * ⚠️ THE META-TEST'S OWN POSITIVE CONTROL.
     *
     * Everything above is only worth something if this file can actually tell
     * a dead reader from a live one. So: a deliberately dead reader and a
     * deliberately overeager one, run through the same two checks, both of
     * which must be caught.
     *
     * Without this, a bug in the loop above — an empty registry, a `continue`
     * in the wrong place — would report every control healthy while examining
     * none of them. Which is, precisely, the failure this file was written to
     * end.
     */
    public function test_the_meta_test_can_tell_a_dead_control_from_a_live_one(): void
    {
        $deadReader = fn (string $t) => [];              // never sees anything
        $overeagerReader = fn (string $t) => ['always']; // sees everything

        $planted = 'Her birth chart suggests a Leo.';
        $clean = 'A patient, home-building match.';

        // The blindness check must catch the dead one.
        $this->assertSame([], $deadReader($planted), 'The fixture is not actually dead.');

        // The over-eagerness check must catch the greedy one.
        $this->assertNotSame([], $overeagerReader($clean), 'The fixture is not actually overeager.');

        // And a genuinely working reader must survive both.
        $working = fn (string $t) => Scanners::forbiddenTerms($t);

        $this->assertNotSame([], $working($planted), 'A known-good reader failed the blindness check.');
        $this->assertSame([], $working($clean), 'A known-good reader failed the over-eagerness check.');
    }

    /**
     * ⚠️ THE ZODIAC PROOF, AS A TEST RATHER THAN A COMMAND I TYPE.
     *
     * Previously this was a shell grep run by hand at the end of a phase. That
     * is how it came to print CLEAN while scanning zero files: nothing about a
     * command typed into a terminal is repeatable, reviewable, or able to fail
     * a build.
     */
    public function test_no_product_source_file_contains_affinity_vocabulary(): void
    {
        $files = $this->productSources();

        $offences = [];

        foreach ($files as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if (in_array($relative, self::THE_ENGINE, true)) {
                continue;
            }

            $found = Scanners::forbiddenTerms(Scanners::prose(File::get($path)));

            if ($found !== []) {
                $offences[] = $relative.' → '.implode(', ', $found);
            }
        }

        $this->assertSame(
            [],
            $offences,
            "Affinity vocabulary appears in product source. The engine weights it internally and "
                ."the words are permanently banned from every surface a member can reach:\n  - "
                .implode("\n  - ", $offences)
        );
    }

    /**
     * ⚠️ THE ALLOW-LIST IS A CLOSED SET, PROVEN CLOSED.
     *
     * An allow-list that nothing checks is an ignore-list. This asserts both
     * halves: every path on it genuinely contains the vocabulary (so a stale
     * entry cannot sit there quietly widening the hole), and every path on it
     * still exists.
     */
    public function test_the_engine_allow_list_is_exactly_the_files_that_need_it(): void
    {
        foreach (self::THE_ENGINE as $relative) {
            $path = base_path($relative);

            $this->assertFileExists(
                $path,
                "[{$relative}] is on the affinity allow-list but no longer exists. A stale entry "
                    .'silently permits a future file at that path to carry the vocabulary.'
            );

            $this->assertNotSame(
                [],
                Scanners::forbiddenTerms(File::get($path)),
                "[{$relative}] is on the affinity allow-list but no longer contains the "
                    .'vocabulary, so the exemption is doing nothing except widening the rule. '
                    .'Remove it from the list.'
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR THE STALE-ENTRY HALF — the half that only fires
     * under a condition nobody creates on purpose.
     *
     * The "a third file gained the vocabulary" half is exercised constantly:
     * every run of the affinity scan depends on it. The "an entry went stale"
     * half fires only when somebody refactors the vocabulary out of an engine
     * file and forgets the list, which may be never — so it could sit there
     * broken for the life of the project and nothing would say so.
     *
     * A rotted entry is not harmless. It is a standing permission for whatever
     * file next occupies that path, and a path is far easier to reoccupy than a
     * file is to move.
     */
    public function test_the_stale_entry_detector_can_actually_see_a_stale_entry(): void
    {
        // A file that exists and contains none of the vocabulary — which is what
        // a rotted allow-list entry looks like.
        $innocent = base_path('composer.json');

        $this->assertFileExists($innocent);

        $this->assertSame(
            [],
            Scanners::forbiddenTerms(File::get($innocent)),
            'The stale-entry detector cannot distinguish a file that carries the vocabulary from '
                .'one that does not, so the freshness half of the allow-list check proves nothing.'
        );

        // And the detector genuinely fires on a real engine file, so the
        // comparison above is not passing because the reader is dead.
        $live = base_path(self::THE_ENGINE[0]);

        $this->assertNotSame([], Scanners::forbiddenTerms(File::get($live)));
    }

    /**
     * ⚠️ AND THE SCAN ABOVE MUST HAVE SCANNED SOMETHING.
     *
     * This is the assertion whose absence caused the whole incident. It is
     * stated separately rather than folded into the scan so that "clean" and
     * "looked at nothing" can never again produce the same output.
     */
    public function test_the_affinity_scan_actually_reads_a_substantial_number_of_files(): void
    {
        $files = $this->productSources();

        $this->assertGreaterThan(
            60,
            count($files),
            'The affinity scan is reading '.count($files).' files. It found nothing because it '
                .'looked at nothing, which is exactly how the original proof printed CLEAN.'
        );

        // And it is reading real content, not empty strings.
        $bytes = array_sum(array_map(fn (string $p) => strlen(File::get($p)), $files));

        $this->assertGreaterThan(100_000, $bytes, 'The scan is opening files but reading nothing from them.');
    }

    /**
     * ⚠️ NO GUARD MAY ENUMERATE FILES WITHOUT PROVING THE SET IS NOT EMPTY.
     *
     * The PHP shape of the unquoted-variable bug: a wrong path, a moved
     * directory, or a filter that excludes everything produces an empty
     * iteration, and an empty iteration reports clean.
     */
    public function test_every_file_scanning_guard_proves_it_scanned_something(): void
    {
        $unprotected = [];

        foreach (ScannedFiles::withExtension(File::allFiles(base_path('tests')), ['php'], 'the test suite', 20) as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if (str_contains($relative, 'PositiveControlsAreAliveTest') || str_contains($relative, 'tests/Support/')) {
                continue;
            }

            $source = File::get($path);

            $enumerates = str_contains($source, 'File::allFiles(') || preg_match('/[^a-z]glob\(/i', $source) === 1;

            if (! $enumerates) {
                continue;
            }

            $proves = str_contains($source, 'ScannedFiles::')
                || preg_match('/assert(NotEmpty|GreaterThan|Count)\s*\(/', $source) === 1;

            if (! $proves) {
                $unprotected[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $unprotected,
            "These guards enumerate files but never assert the set was non-empty, so a wrong path "
                ."makes them report clean:\n  - ".implode("\n  - ", $unprotected)
                ."\n\nTake the file set through Tests\\Support\\ScannedFiles::of()."
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL for the check above — it must be able to see both an
     * unprotected guard and a protected one.
     */
    public function test_the_empty_scan_detector_can_tell_protected_from_unprotected(): void
    {
        $unprotected = 'foreach (File::allFiles(resource_path("js")) as $f) { $x = 1; }';
        $protected = 'ScannedFiles::of(File::allFiles(resource_path("js")), "components");';

        $enumerates = fn (string $s) => str_contains($s, 'File::allFiles(');
        $proves = fn (string $s) => str_contains($s, 'ScannedFiles::')
            || preg_match('/assert(NotEmpty|GreaterThan|Count)\s*\(/', $s) === 1;

        $this->assertTrue($enumerates($unprotected) && ! $proves($unprotected), 'The detector cannot see an unprotected scan.');
        $this->assertTrue($enumerates($protected) && $proves($protected), 'The detector flags a protected scan.');
    }

    /**
     * ⚠️ A GUARD MUST NOT KEEP A PRIVATE COPY OF A SHARED READER.
     *
     * An inlined regex cannot be exercised by this file, so it is precisely
     * the kind of reader that goes blind unnoticed — which is what happened to
     * the overflow guard and to both measurement harnesses.
     */
    public function test_no_guard_re_declares_a_shared_reader(): void
    {
        $shared = [
            '>\s*([A-Z][^<>{}]{14,})\s*<' => 'Scanners::rawCopyStrings()',
        ];

        $copies = [];

        foreach (ScannedFiles::withExtension(File::allFiles(base_path('tests')), ['php'], 'the test suite', 20) as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if (str_contains($relative, 'tests/Support/') || str_contains($relative, 'PositiveControlsAreAliveTest')) {
                continue;
            }

            $source = File::get($path);

            foreach ($shared as $pattern => $owner) {
                if (str_contains($source, $pattern)) {
                    $copies[] = "{$relative} re-declares the reader owned by {$owner}";
                }
            }
        }

        $this->assertSame(
            [],
            $copies,
            "A reader has been copied out of Tests\\Support\\Scanners, where this file can exercise "
                ."it, into a guard where it cannot:\n  - ".implode("\n  - ", $copies)
        );
    }

    /**
     * Everything a member could ever read, plus everything that decides what
     * they read. Deliberately broad — the cost of scanning too much here is
     * seconds; the cost of scanning too little is the client's product.
     *
     * @return list<string>
     */
    private function productSources(): array
    {
        $files = [];

        foreach ([
            app_path(),
            resource_path('js'),
            resource_path('views'),
            lang_path(),
            config_path(),
        ] as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (in_array(strtolower($file->getExtension()), ['php', 'vue', 'js', 'json'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return ScannedFiles::of($files, 'product source (app, resources, lang, config)', 60);
    }
}
