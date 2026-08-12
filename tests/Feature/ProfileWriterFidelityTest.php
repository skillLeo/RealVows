<?php

namespace Tests\Feature;

use App\Services\Ai\ProfileWriterFidelity;
use Tests\TestCase;

/**
 * ⚠️ THE REWRITE MAY ONLY USE FACTS THE MEMBER SUPPLIED.
 *
 * A profile stating untrue things is deception, and a more direct harm than the
 * matching mechanism this product just removed: another member reads it,
 * believes it, and decides whether to meet a stranger partly on it.
 *
 * ⚠️ THE PROMPT IS NOT THE CONTROL. "The system prompt says not to invent
 * things" is a request, not a guarantee. Models comply most of the time, which
 * is the worst failure rate there is — frequent enough to look reliable, rare
 * enough that nobody is watching when it does not.
 */
class ProfileWriterFidelityTest extends TestCase
{
    private ProfileWriterFidelity $fidelity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fidelity = new ProfileWriterFidelity;
    }

    /** ⚠️ THE CASE THE FEATURE EXISTS FOR: rough English, tidied, nothing added. */
    public function test_a_faithful_rewrite_passes(): void
    {
        $original = 'i am teacher in Kingston. i like cooking and reading books. '
            .'i want family one day and someone who is kind.';

        $rewrite = 'I am a teacher in Kingston. I enjoy cooking and reading. '
            .'I would like a family one day, and someone kind.';

        $this->assertSame([], $this->fidelity->unsupportedClaims($original, $rewrite));
        $this->assertTrue($this->fidelity->isFaithful($original, $rewrite));
    }

    /**
     * ⚠️ THE HALLUCINATING FIXTURE THE BRIEF REQUIRES. If this ever passes,
     * the check has stopped working and the feature is unsafe to ship.
     */
    public function test_an_invented_place_is_caught(): void
    {
        $original = 'i am teacher. i like cooking.';
        $rewrite = 'I am a teacher in Montego Bay who loves to cook.';

        $this->assertContains('Montego', $this->fidelity->unsupportedClaims($original, $rewrite));
        $this->assertFalse($this->fidelity->isFaithful($original, $rewrite));
    }

    public function test_an_invented_employer_is_caught(): void
    {
        $original = 'i work in a bank. i am quiet person.';
        $rewrite = 'I work at Scotiabank and I am a quiet person.';

        $this->assertContains('Scotiabank', $this->fidelity->unsupportedClaims($original, $rewrite));
    }

    /**
     * ⚠️ NUMBERS ARE FACTS ABOUT SOMEBODY'S LIFE. A model that rounds or
     * embellishes a quantity has changed one, and a reader cannot tell.
     */
    public function test_an_invented_quantity_is_caught(): void
    {
        $original = 'i have children and i work long hours.';
        $rewrite = 'I have 3 children and I work long hours.';

        $this->assertContains('3', $this->fidelity->unsupportedClaims($original, $rewrite));
    }

    /**
     * ⚠️ THE OVER-EAGER HALF. A check that flags ordinary sentence openers
     * would reject every rewrite, and the feature would be switched off within
     * a day — which is the same outcome as having no check.
     */
    public function test_ordinary_capitalised_words_are_not_treated_as_invented(): void
    {
        $original = 'i like quiet evenings and i am looking for someone serious.';
        $rewrite = 'I like quiet evenings. The person I am looking for is serious. '
            .'When we meet, I would like it to matter.';

        $this->assertSame(
            [],
            $this->fidelity->unsupportedClaims($original, $rewrite),
            'Sentence openers were treated as invented facts. A check that rejects every '
                .'rewrite is switched off within a day, which is the same as having none.'
        );
    }

    /** A name the member gave may be kept. */
    public function test_a_name_the_member_supplied_survives(): void
    {
        $original = 'my name is Aisha and i live in Kingston.';
        $rewrite = 'My name is Aisha and I live in Kingston.';

        $this->assertSame([], $this->fidelity->unsupportedClaims($original, $rewrite));
    }

    /**
     * ⚠️ CASE AND PUNCTUATION MUST NOT DEFEAT THE COMPARISON. Members type in
     * lower case; the rewrite capitalises. If that alone read as invention the
     * check would fire on every single rewrite.
     */
    public function test_the_comparison_survives_capitalisation_and_punctuation(): void
    {
        $original = 'i studied at the university of the west indies.';
        $rewrite = 'I studied at the University of the West Indies.';

        $this->assertSame([], $this->fidelity->unsupportedClaims($original, $rewrite));
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR THE WHOLE CLASS. Several assertions above expect
     * an empty array, which is also what a checker that examines nothing
     * returns. This proves it is genuinely reading both strings.
     */
    public function test_the_checker_is_not_simply_returning_nothing(): void
    {
        $found = $this->fidelity->unsupportedClaims(
            'i am a teacher.',
            'I am a teacher at Immaculate Conception in Kingston with 2 dogs.',
        );

        $this->assertNotEmpty($found, 'The checker returns nothing for anything, so every empty '
            .'expectation above is vacuous.');

        foreach (['Immaculate', 'Conception', 'Kingston', '2'] as $expected) {
            $this->assertContains($expected, $found);
        }
    }
}
