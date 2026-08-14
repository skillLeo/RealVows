<?php

namespace Tests\Feature;

use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ A PUBLIC PAGE SAYS WHAT THE PRODUCT DOES. NEVER HOW IT DOES IT.
 *
 * This is the client's instruction, given three times, and it has two halves he
 * cares about for different reasons.
 *
 * ── ⚠️ THE VERIFICATION METHOD ────────────────────────────────────────────
 *
 * He circled that paragraph and wrote "this is private information". He is
 * right for a stronger reason than presentation: publishing an anti-fraud
 * method publishes what somebody needs in order to prepare for it. The outcome
 * may be stated. The mechanism may not.
 *
 * ── ⚠️ WHAT A MESSAGE LOOKS LIKE ──────────────────────────────────────────
 *
 * In his words: "we should not tell them anything about what the email will say
 * or what the WhatsApp message will say. Just tell them they will receive an
 * email and a WhatsApp, but do not give them an idea what it will say or what
 * it will look like."
 *
 * That the channels exist is a fact and may be stated. Their contents may not,
 * and neither may a rendered imitation of one — a mock notification on the home
 * page tells a reader more about what arrives than any sentence would.
 *
 * ── ⚠️ WHY THIS IS A TEST AND NOT A STYLE NOTE ────────────────────────────
 *
 * Marketing copy is rewritten by whoever is closest to it, usually in a hurry,
 * usually to fill a space that looks empty. "Explain the scoring" is the single
 * most natural instinct a person has when looking at a page about matching, and
 * it is the instinct this rule exists to stop.
 */
class PublicPagesDescribeNoMechanismTest extends TestCase
{
    private const PAGES = ['/', '/about', '/how-it-works', '/questions'];

    /**
     * Phrases that describe HOW rather than WHAT.
     *
     * ⚠️ NOT A BAN ON THE WORDS THEMSELVES. "Verified" is the outcome and
     * appears all over these pages correctly. "Photographs" is a fact about
     * what is collected. Each entry below is a phrase that could only be
     * written by somebody explaining the machinery.
     */
    private const MECHANISM = [
        'handwritten' => 'the verification method',
        'hold the code' => 'the verification method',
        'holding a code' => 'the verification method',
        'holding the code' => 'the verification method',
        'six-digit' => 'the verification method',
        'on a note' => 'the verification method',
        'selfie with' => 'the verification method',

        'built from what you tell us' => 'how the score is calculated',
        'built from what both people told us' => 'how the score is calculated',
        'the values you rank highest' => 'how the score is calculated',
        'both sides are weighed' => 'how the score is calculated',
        'out of 100' => 'how the score is calculated',

        'four photos' => 'a specification rather than a promise',
        'seven guided steps' => 'a specification rather than a promise',
    ];

    public function test_no_public_page_explains_how_anything_works(): void
    {
        $offences = [];
        $scanned = 0;

        foreach (self::PAGES as $path) {
            $text = strtolower(Scanners::visibleText($this->get($path)->assertOk()->getContent()));

            $this->assertNotEmpty($text, "{$path} rendered nothing, so scanning it proves nothing.");
            $scanned++;

            foreach (self::MECHANISM as $phrase => $why) {
                if (str_contains($text, $phrase)) {
                    $offences[] = "{$path} → “{$phrase}” ({$why})";
                }
            }
        }

        $this->assertSame(count(self::PAGES), $scanned);

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'These public pages explain how the product works rather than what it does.',
            'The client has asked three times for less, and for the verification method and the',
            'contents of a message never to be described at all.',
        ], $offences)));
    }

    /**
     * ⚠️ NO IMITATION OF A MESSAGE, EITHER.
     *
     * A mock notification rendered on the home page shows a reader exactly what
     * an introduction looks like when it arrives, which is the thing the client
     * asked not to disclose. It is harder to spot than a sentence because it is
     * markup rather than prose, and it says more.
     */
    public function test_no_public_page_imitates_a_message_arriving(): void
    {
        $offences = [];

        foreach (self::PAGES as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach ([
                'open this introduction from' => 'a mock notification body',
                'tap to open it' => 'a mock notification interaction',
                'this is how an introduction arrives' => 'a demonstration of delivery',
            ] as $needle => $why) {
                if (str_contains(strtolower($html), $needle)) {
                    $offences[] = "{$path} → {$why}";
                }
            }
        }

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'A public page imitates an introduction arriving. The client asked that members be',
            'told an email and a WhatsApp will come, and nothing about what either will say or',
            'look like. A rendered mock says more than any sentence could.',
        ], $offences)));
    }

    /**
     * ⚠️ POSITIVE CONTROL, IN BOTH DIRECTIONS.
     *
     * Every assertion above is an empty-array check over a phrase list. A list
     * that had drifted out of the copy entirely — because somebody reworded a
     * page — would report a clean sweep while the reader learned nothing about
     * whether the rule holds.
     */
    public function test_the_reader_catches_a_mechanism_and_leaves_honest_copy_alone(): void
    {
        $bad = strtolower('Take a photo holding the code we issued, and your score is built from '
            .'what you tell us, out of 100.');

        $caught = array_keys(array_filter(
            self::MECHANISM,
            fn ($why, $phrase) => str_contains($bad, $phrase),
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertNotEmpty($caught, 'The reader saw nothing wrong with a paragraph of pure mechanism.');
        $this->assertContains('holding the code', $caught);
        $this->assertContains('out of 100', $caught);

        // And the other direction: ordinary honest copy must survive untouched.
        $good = strtolower('Everyone here has been checked before anyone is introduced to them. '
            .'Joining is free, and your introductions come to you.');

        $this->assertSame([], array_keys(array_filter(
            self::MECHANISM,
            fn ($why, $phrase) => str_contains($good, $phrase),
            ARRAY_FILTER_USE_BOTH,
        )), 'The reader flagged ordinary copy, which is how a guard gets overridden and then ignored.');
    }
}
