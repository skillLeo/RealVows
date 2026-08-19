<?php

namespace Tests\Feature;

use App\Services\Ai\ProfileWriterFidelity;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ⚠️ THE PROMISE MADE TO A MEMBER, HELD BY CODE RATHER THAN BY A PROMPT.
 *
 * What the member is told: you write your profile in your own rough words, and
 * this fixes your English. It never invents anything and never puts words in
 * your mouth.
 *
 * ⚠️ THE FIRST VERSION OF THIS CHECK DID NOT HOLD THAT PROMISE. It looked at
 * capitalised words and digits, so measured against realistic invention it
 * caught two cases in six:
 *
 *   "I like cooking and hiking"      → slipped through
 *   "a teacher with two children"    → slipped through
 *   "a patient, adventurous teacher" → slipped through
 *   "a teacher in Kingston"          → caught
 *   "with 3 children"                → caught
 *   dropping a fact                  → nothing even looked
 *
 * Most invention is lowercase. A hobby, a temperament and a number written as a
 * word are precisely what a model reaches for when asked to make something read
 * better, and none of them are capitalised.
 */
class TheWriterOnlyProofreadsTest extends TestCase
{
    private ProfileWriterFidelity $fidelity;

    /** Deliberately rough. This is what a member actually types. */
    private const OWN_WORDS = 'i am a teacher and i like cooking. i want a kind husband. i has two kid.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fidelity = new ProfileWriterFidelity;
    }

    #[DataProvider('inventions')]
    public function test_it_refuses_anything_the_member_did_not_write(string $why, string $rewrite): void
    {
        $this->assertFalse(
            $this->fidelity->isFaithful(self::OWN_WORDS, $rewrite),
            "A rewrite that {$why} was accepted. Another member reads this and decides whether "
                .'to meet a stranger partly on the strength of it.',
        );
    }

    public static function inventions(): array
    {
        return [
            'adds a hobby in lowercase' => ['adds a hobby', 'I am a teacher and I like cooking and hiking. I want a kind husband. I have two kids.'],
            'adds a trait' => ['adds a personality', 'I am a patient and adventurous teacher who likes cooking. I want a kind husband. I have two kids.'],
            'changes a number written as a word' => ['changes a number', 'I am a teacher and I like cooking. I want a kind husband. I have three kids.'],
            'adds a digit' => ['adds a number', 'I am a teacher and I like cooking. I want a kind husband. I have 4 kids.'],
            'adds a place' => ['adds a place', 'I am a teacher in Kingston and I like cooking. I want a kind husband. I have two kids.'],
            'adds an employer' => ['adds an employer', 'I am a teacher at Immaculate and I like cooking. I want a kind husband. I have two kids.'],
            'adds a whole sentence' => ['adds a sentence', 'I am a teacher and I like cooking. I want a kind husband. I have two kids. Family is everything to me.'],
            'swaps a word for a nicer one' => ['reworded her', 'I am a teacher and I enjoy cooking. I want a kind husband. I have two kids.'],
            'drops her children' => ['dropped a fact', 'I am a teacher and I like cooking. I want a kind husband.'],
            'drops her work' => ['dropped a fact', 'I like cooking. I want a kind husband. I have two kids.'],
        ];
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND THE DIRECTION THAT KEEPS THE FEATURE ALIVE.
     *
     * A check that refused everything would satisfy every assertion above and
     * would be switched off within a week, which is the same as having none.
     * These are real corrections: capitals, articles, punctuation, verb
     * agreement and a plural. Not one new idea between them.
     */
    #[DataProvider('properProofreads')]
    public function test_it_accepts_an_actual_correction(string $rewrite): void
    {
        $this->assertTrue(
            $this->fidelity->isFaithful(self::OWN_WORDS, $rewrite),
            'A genuine grammar fix was refused: '
                .implode(', ', $this->fidelity->unsupportedClaims(self::OWN_WORDS, $rewrite))
                .'. A tool that rejects correct work teaches somebody to stop using it.',
        );
    }

    public static function properProofreads(): array
    {
        return [
            'capitals, articles, agreement' => ['I am a teacher and I like cooking. I want a kind husband. I have two kids.'],
            'resentenced' => ['I am a teacher. I like cooking, and I have two kids. I want a kind husband.'],
            'reordered' => ['I have two kids. I am a teacher and I like cooking. I want a kind husband.'],
        ];
    }

    /**
     * ⚠️ THE READER MUST READ. Every assertion above is "refused" or
     * "accepted", and a checker whose word lists had been emptied would answer
     * one of those for everything.
     */
    public function test_the_checker_is_not_answering_the_same_thing_to_everything(): void
    {
        $this->assertNotEmpty(
            $this->fidelity->unsupportedClaims('i am a teacher.', 'I am a teacher in Kingston with 2 dogs.'),
            'The checker finds nothing in an obviously invented rewrite, so every refusal above '
                .'is vacuous.',
        );

        $this->assertSame(
            [],
            $this->fidelity->unsupportedClaims('i am a teacher.', 'I am a teacher.'),
            'The checker objects to an identical sentence, so every acceptance above is luck.',
        );
    }

    /**
     * ⚠️ THE WORD THE MEMBER WROTE IS THE WORD THAT COMES BACK.
     *
     * This is the sentence the client repeats to people: it does not put words
     * in your mouth. A synonym is a word in her mouth, however flattering.
     */
    public function test_a_synonym_is_treated_as_an_invention(): void
    {
        $claims = $this->fidelity->unsupportedClaims(
            'i like cooking.',
            'I enjoy cooking.',
        );

        $this->assertContains('enjoy', $claims);
    }
}
