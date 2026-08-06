<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicSurfaces;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ THE PROSE MUST NOT READ AS THOUGH A MACHINE WROTE IT.
 *
 * ── Why this is a build failure and not a style preference ────────────────
 *
 * From the client, after reading the site properly for the first time:
 *
 *   "Ask the AI to fix the grammar. English is grammar. The people who read
 *    this, who will be serious, they will look at the grammar and they would
 *    think somebody just came up with this and tried to rig this up in a few
 *    days. The English has to be proper."
 *
 * He is right, and the reasoning is sharper than it first sounds. RealVows
 * asks a stranger to hand over their photographs, their faith, their income
 * bracket and their intentions about the rest of their life. The only evidence
 * they have that this is a serious operation, before they commit anything, is
 * how it is written. Prose that reads as generated undermines the entire
 * proposition more directly than a missing feature would.
 *
 * ── ⚠️ WHAT IS CHECKED HERE AND WHAT IS NOT ───────────────────────────────
 *
 * Machine-check what is mechanically checkable. Everything else is read by a
 * person.
 *
 * CHECKED: em dashes, en dashes, semicolons, banned vocabulary, contractions
 * on the public and legal pages. These are unambiguous. A dash either is or is
 * not in the string.
 *
 * NOT CHECKED, DELIBERATELY: comma splices, register drift, the antithesis
 * habit, rule-of-three lists, uniform sentence rhythm. Every one of those
 * needs judgement, and a reader that guesses at them produces false positives.
 * ⚠️ A GUARD THAT CRIES WOLF IS WORSE THAN NO GUARD, because the first thing
 * it teaches everybody is to override guards. Those faults were fixed by
 * reading the pages, and they stay fixed by reading them again.
 */
class ProseStyleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ CONTRACTIONS ARE ALLOWED INSIDE THE PRODUCT, NOT ON THE WAY IN.
     *
     * The marketing and legal pages are persuasive and contractual, and they
     * hold a formal register. The authenticated product is instructional, and
     * "you'll" reads warmer than "you will" when somebody is halfway through
     * a form.
     *
     * What is not allowed is mixing them on one surface, which is what was
     * live: "A real person checks you're you" sat two lines from "You will not
     * spend evenings scrolling". Inconsistency of register IS the error, and a
     * reader feels it without being able to name it.
     */
    private const FORMAL_SURFACES = [
        '/', '/how-it-works', '/about',
        '/terms', '/privacy', '/community-guidelines', '/refunds',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    public function test_no_public_page_uses_a_dash_or_a_semicolon_as_punctuation(): void
    {
        $offences = [];

        foreach (PublicSurfaces::paths() as $path) {
            $text = Scanners::visibleText($this->get($path)->getContent());

            foreach (Scanners::sentences($text) as $sentence) {
                foreach (Scanners::aiPunctuation($sentence) as $mark) {
                    $offences[] = "{$path} [{$mark}] {$sentence}";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A dash or semicolon is being used as punctuation on a public page. Do not swap it "
                ."for a comma: a sentence that needs one to hold together is two "
                ."sentences.\n  ".implode("\n  ", $offences)
        );
    }

    public function test_no_public_page_uses_banned_vocabulary(): void
    {
        $offences = [];

        foreach (PublicSurfaces::paths() as $path) {
            $text = Scanners::visibleText($this->get($path)->getContent());

            foreach (Scanners::bannedVocabulary($text) as $word) {
                $offences[] = "{$path} contains [{$word}]";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", $offences));
    }

    public function test_the_formal_surfaces_hold_one_register(): void
    {
        $offences = [];

        foreach (self::FORMAL_SURFACES as $path) {
            $text = Scanners::visibleText($this->get($path)->getContent());

            foreach (Scanners::contractions($text) as $contraction) {
                $offences[] = "{$path} uses [{$contraction}]";
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A contraction appeared on a marketing or legal page. These surfaces hold a formal "
                ."register throughout, because mixing the two on one page is the thing a reader "
                ."feels without being able to name:\n  ".implode("\n  ", $offences)
        );
    }

    /**
     * ⚠️ THE COPY DECK IS ALLOWED CONTRACTIONS, AND MUST STILL BE CONSISTENT
     * ABOUT DASHES. The deck feeds the authenticated product, where "you'll"
     * is right and an em dash is still a tell.
     */
    public function test_the_copy_deck_carries_no_dashes_or_semicolons(): void
    {
        $offences = [];

        foreach ($this->flatten(trans('realvows')) as $key => $value) {
            foreach (Scanners::aiPunctuation($value) as $mark) {
                $offences[] = "realvows.{$key} [{$mark}] {$value}";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", $offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), BOTH DIRECTIONS.
     *
     * The planted strings are the ones that were actually live. The clean
     * samples are the real replacements, plus the two shapes that broke the
     * first version of the contraction reader.
     */
    public function test_the_readers_catch_what_shipped_and_leave_good_prose_alone(): void
    {
        // The banner that was live, and the sentence that replaced it.
        $this->assertSame(
            ['—'],
            Scanners::aiPunctuation('the sign-in buttons are open — not the live platform.'),
            'The punctuation reader cannot see the dash that was on the live home page.'
        );
        $this->assertSame(
            [],
            Scanners::aiPunctuation('This is not the live platform. Every member shown here is invented.'),
            'The punctuation reader flags clean prose.'
        );

        $this->assertSame(
            ["you're"],
            Scanners::contractions("A real person checks you're you, usually within a day"),
            'The contraction reader cannot see the contraction that was live.'
        );

        /*
         * ⚠️ THE OVER-EAGER HALF. Both of these are possessives and both were
         * flagged by the first version of this reader. A guard that fires on
         * "one person's wishes" gets switched off within a day.
         */
        $this->assertSame(
            [],
            Scanners::contractions("a match is never one person's wishes imposed on another's"),
            'The contraction reader flags possessives, which would make it noise.'
        );

        $this->assertSame(
            ['journey'],
            Scanners::bannedVocabulary('Begin your journey with us.'),
            'The vocabulary reader cannot see the word it exists for.'
        );
        $this->assertSame(
            [],
            Scanners::bannedVocabulary('You will not spend your evenings scrolling.'),
            'The vocabulary reader flags ordinary writing.'
        );
    }

    /** @return \Generator<string, string> */
    private function flatten(array $values, string $prefix = ''): \Generator
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                yield from $this->flatten($value, $path);
            } elseif (is_string($value)) {
                yield $path => $value;
            }
        }
    }
}
