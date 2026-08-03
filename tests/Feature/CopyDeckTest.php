<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ A COPY DECK NOTHING REFERENCES IS WORSE THAN NO COPY DECK.
 *
 * `lang/en/realvows.php` shipped with 64 carefully written strings and ZERO
 * references anywhere in the codebase. It read as complete in a file listing
 * and did nothing at all — the same failure class as the vacuous assertions
 * and the blinded overflow guard: something that looks like it passes while
 * checking nothing.
 *
 * Wiring it up once fixes it once. This is what stops it drifting back, by
 * failing the build the moment a component carries a sentence inline.
 *
 * ── What counts as a violation ────────────────────────────────────────────
 *
 * A user-facing string is text a member reads. It is NOT a class name, an
 * icon key, a route, a date format, or an aria-label describing structure.
 * The heuristic below looks only at text that is the entire content of an
 * element — `>Some sentence<` — which is where copy actually lives.
 */
class CopyDeckTest extends TestCase
{
    /**
     * ⚠️ THE ALLOW-LIST IS A LIST OF ARGUMENTS, NOT A DUMPING GROUND.
     *
     * Every entry needs a reason, because an allow-list that grows without
     * one is just the check being switched off slowly.
     */
    private const ALLOWED = [
        // The public marketing pages are Blade, not Vue, and are deliberately
        // NOT in the deck: they are a single continuous piece of writing that
        // is edited as prose, and breaking them into keys would make the page
        // impossible to read in one sitting. The deck exists for the product,
        // where the same phrase must appear identically in six places.
        'resources/views/public/',

        // Emails are Blade and are edited as whole letters, same argument.
        'resources/views/emails/',

        // The receipt is a printable document, edited as a document.
        'resources/views/receipt.blade.php',
        'resources/views/legal.blade.php',

        /*
         * ⚠️ THE ADMIN PANEL IS NOT MEMBER-FACING, and that is the whole
         * distinction the deck draws.
         *
         * Its own header gives the reason: the deck exists so tone stays
         * consistent "because it is all visible at once", and so the client
         * can be shown the wording without the code. Folding two hundred
         * operator strings — column headings, audit descriptions, queue
         * labels — into the same file destroys both properties. The member
         * copy would no longer be readable in one sitting, which is what
         * makes the deck useful.
         *
         * Admin copy is read by one person who also owns the product. It has
         * no drift problem to solve: "Not verified yet" appearing in three
         * member screens is a real risk, the same phrase in one admin table
         * is not.
         */
        'resources/js/Pages/Admin/',
    ];

    /** Text that is not copy, however sentence-like it looks. */
    private const NOT_COPY = [
        // Structural aria text describing the interface, not addressing anyone.
        'Skip to content',
        // A brand name is never translated and never varies.
        'RealVows',
        'RV',
    ];

    /** @return array<string, list<string>> file => offending strings */
    private function rawStrings(): array
    {
        $offences = [];

        foreach (File::allFiles(resource_path('js')) as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $relative = str_replace(base_path().'/', '', $file->getPathname());

            foreach (self::ALLOWED as $allowed) {
                if (str_contains($relative, $allowed)) {
                    continue 2;
                }
            }

            $contents = File::get($file->getPathname());

            if (! str_contains($contents, '<template>')) {
                continue;
            }

            $template = substr(
                $contents,
                strpos($contents, '<template>'),
                strrpos($contents, '</template>') - strpos($contents, '<template>')
            );

            /*
             * Text that is the ENTIRE content of an element and reads like a
             * sentence: starts with a capital, at least three words. That is
             * what copy looks like; a label like "Save" or a class name is
             * not caught, and neither is an interpolation.
             */
            preg_match_all('/>\s*([A-Z][^<>{}]{14,})\s*</', $template, $matches);

            foreach ($matches[1] as $found) {
                $text = trim($found);

                if (in_array($text, self::NOT_COPY, true)) {
                    continue;
                }

                // Needs at least three words to be a sentence rather than a label.
                if (str_word_count($text) < 3) {
                    continue;
                }

                $offences[$relative][] = $text;
            }
        }

        return $offences;
    }

    /** ⚠️ THE CHECK THIS CLASS EXISTS FOR. */
    public function test_no_component_carries_a_user_facing_string_inline(): void
    {
        $offences = $this->rawStrings();

        $report = [];

        foreach ($offences as $file => $strings) {
            foreach (array_unique($strings) as $string) {
                $report[] = "{$file}: \"{$string}\"";
            }
        }

        $this->assertSame(
            [],
            $report,
            "These sentences live in components instead of the copy deck:\n  "
                .implode("\n  ", $report)
                ."\n\nMove them to lang/en/realvows.php and read them with \$t('group.key'). "
                .'The deck exists so the same phrase cannot drift between the six places it '
                .'appears, and so the client can be shown the wording without the code.'
        );
    }

    /**
     * ⚠️ THE DECK MUST BE REACHABLE, which is the part that was missing for
     * weeks. A deck with no reader is decoration.
     */
    public function test_the_deck_is_actually_wired_up(): void
    {
        $app = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "app.config.globalProperties.\$t",
            $app,
            'There is no $t helper, so no component can read the deck even if it wanted to.'
        );

        $used = 0;

        foreach (File::allFiles(resource_path('js')) as $file) {
            if ($file->getExtension() === 'vue') {
                $used += substr_count(File::get($file->getPathname()), '$t(');
            }
        }

        $this->assertGreaterThan(
            20,
            $used,
            "Only {$used} components read the deck. It shipped once with 64 keys and zero "
                .'references — complete in a file listing and doing nothing.'
        );
    }

    /**
     * ⚠️ THE BUNDLED COPY MUST MATCH THE PHP DECK.
     *
     * The deck is compiled into the JS bundle rather than shared as an Inertia
     * prop, because sharing it put every string in the product into every JSON
     * response — including "report", which broke the guard that stops a
     * reported member learning they were reported.
     *
     * That trade means two files, so this asserts they cannot drift.
     */
    public function test_the_bundled_copy_matches_the_source_deck(): void
    {
        $generated = resource_path('js/copy.generated.json');

        $this->assertFileExists($generated, 'The bundled copy is missing; every $t() returns its key.');

        $this->assertSame(
            trans('realvows'),
            json_decode(File::get($generated), true),
            'resources/js/copy.generated.json has drifted from lang/en/realvows.php. Regenerate '
                .'it: php -r \'file_put_contents("resources/js/copy.generated.json", '
                .'json_encode(require "lang/en/realvows.php", JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\\n");\''
        );
    }

    /** ⚠️ Tone rules from the deck's own header, enforced. */
    public function test_the_product_never_shouts(): void
    {
        $deck = File::get(lang_path('en/realvows.php'));

        // Only the strings, not the comments explaining the rule.
        preg_match_all("/=> '((?:[^'\\\\]|\\\\.)*)'/", $deck, $strings);

        $this->assertNotEmpty($strings[1], 'The string reader found nothing in the deck.');

        foreach ($strings[1] as $string) {
            $this->assertStringNotContainsString(
                '!',
                $string,
                "\"{$string}\" contains an exclamation mark. This is a marriage platform, not a "
                    .'sale — nothing in the product shouts.'
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The scan is a negative assertion, so it is
     * worth nothing unless it can see a real offence.
     */
    public function test_the_scan_can_see_a_raw_string(): void
    {
        $offending = '<template><div><p>We could not find anyone for you today.</p></div></template>';

        preg_match_all('/>\s*([A-Z][^<>{}]{14,})\s*</', $offending, $m);

        $this->assertNotEmpty(
            $m[1],
            'The reader cannot see a sentence sitting in a template, so it proves nothing about '
                .'the real components.'
        );

        $this->assertSame('We could not find anyone for you today.', trim($m[1][0]));

        // And it does NOT flag an interpolated string or a short label.
        preg_match_all('/>\s*([A-Z][^<>{}]{14,})\s*</', '<p>{{ $t(\'a.b\') }}</p><span>Save</span>', $clean);
        $this->assertSame([], $clean[1], 'The reader flags migrated copy, which would make it unusable.');
    }
}
