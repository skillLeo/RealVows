<?php

namespace App\Services\Ai;

/**
 * Does the rewrite say anything the member did not, or drop anything they did?
 *
 * ══════════════════════════════════════════════════════════════════════════
 * ⚠️ A PROFILE THAT STATES UNTRUE THINGS IS DECEPTION
 * ══════════════════════════════════════════════════════════════════════════
 *
 * Another member reads this and decides whether to meet a stranger partly on
 * the strength of it. Inventing a hobby, a child or a hometown is not a
 * cosmetic error.
 *
 * ⚠️ THE CHECK RUNS ON THE OUTPUT, NOT ON THE PROMPT. "The system prompt tells
 * it not to invent things" is a request, not a control. Models comply most of
 * the time, which is the worst possible failure rate: frequent enough to look
 * reliable, rare enough that nobody is watching when it does not.
 *
 * ── ⚠️ WHY THIS WAS REWRITTEN, AND WHAT IT USED TO MISS ────────────────────
 *
 * The first version checked capitalised words and digits. Measured against
 * realistic invention it caught two cases out of six:
 *
 *   "I like cooking and HIKING"        → slipped through (lowercase)
 *   "a teacher with TWO CHILDREN"      → slipped through (number as a word)
 *   "a PATIENT, ADVENTUROUS teacher"   → slipped through (adjectives)
 *   "a teacher in Kingston"            → caught
 *   "with 3 children"                  → caught
 *   dropping a fact entirely           → slipped through (nothing looked)
 *
 * Most invention is lowercase. A hobby, a temperament and a spelled-out number
 * are exactly what a model reaches for when asked to make something read
 * better, and none of them are capitalised.
 *
 * ── ⚠️ SO THE TEST IS INVERTED: AN ALLOWLIST, NOT A BLOCKLIST ─────────────
 *
 * Every meaningful word in the rewrite must come from the member. The only
 * words it may introduce are grammatical: articles, prepositions, pronouns,
 * conjunctions, auxiliaries and the irregular forms a correction produces
 * ("has" becoming "have", "kid" becoming "kids").
 *
 * That is deliberately strict, and it makes this a grammar and spelling tool
 * rather than a writer. Swapping "like" for "enjoy" is refused, because the
 * member did not write "enjoy" and the product's promise is that these are
 * their own words. When it refuses, the member simply keeps what they typed.
 */
class ProfileWriterFidelity
{
    /**
     * ⚠️ THE ONLY WORDS A REWRITE MAY INTRODUCE, AND EVERY ADDITION HERE
     * WIDENS WHAT A MODEL IS ALLOWED TO INVENT.
     *
     * Grammar words carry no claim about a person. "The", "and" and "because"
     * cannot make somebody a teacher in Kingston with two children. Anything
     * that CAN is not in this list.
     *
     * @var list<string>
     */
    private const GRAMMAR = [
        // Articles and determiners.
        'a', 'an', 'the', 'this', 'that', 'these', 'those', 'some', 'any', 'each',
        'every', 'both', 'other', 'another', 'such', 'own',
        // Pronouns and possessives.
        'i', 'me', 'my', 'mine', 'myself', 'we', 'us', 'our', 'ours',
        'you', 'your', 'yours', 'he', 'him', 'his', 'she', 'her', 'hers',
        'it', 'its', 'they', 'them', 'their', 'theirs', 'who', 'whom', 'whose',
        'which', 'what', 'someone', 'somebody', 'something', 'anyone', 'anything',
        // Auxiliaries and the common irregular forms a correction produces.
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing',
        'will', 'would', 'can', 'could', 'shall', 'should', 'may', 'might', 'must',
        // Conjunctions and connectives.
        'and', 'or', 'but', 'nor', 'so', 'yet', 'if', 'then', 'than', 'because',
        'although', 'though', 'while', 'whereas', 'however', 'also', 'as', 'when',
        'where', 'whether', 'since', 'until', 'unless',
        // Prepositions.
        'about', 'above', 'across', 'after', 'against', 'along', 'among', 'around',
        'at', 'before', 'behind', 'below', 'beside', 'between', 'beyond', 'by',
        'down', 'during', 'for', 'from', 'in', 'inside', 'into', 'near', 'of',
        'off', 'on', 'onto', 'out', 'outside', 'over', 'through', 'to', 'toward',
        'towards', 'under', 'up', 'upon', 'with', 'within', 'without',
        // Negation and degree, which qualify rather than assert.
        'no', 'not', 'nothing', 'never', 'very', 'too', 'quite', 'rather',
        'there', 'here', 'yes', 'please', 'thank', 'thanks',
    ];

    /**
     * ⚠️ NUMBERS AS WORDS COUNT AS FACTS.
     *
     * "Two children" changes somebody's life as much as "2 children" does, and
     * the previous version's own comment claimed to cover this while the code
     * only looked at digits.
     *
     * @var list<string>
     */
    private const NUMBER_WORDS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight',
        'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen',
        'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 'thirty',
        'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred',
        'thousand', 'first', 'second', 'third', 'fourth', 'fifth',
        'once', 'twice', 'single', 'couple', 'few', 'several', 'many', 'much',
    ];

    /**
     * Every claim in the rewrite the member's own words do not support.
     *
     * @return list<string>
     */
    public function unsupportedClaims(string $original, string $rewrite): array
    {
        $known = $this->stems($original);
        $offences = [];

        foreach ($this->words($rewrite) as $word) {
            if (in_array($word, self::GRAMMAR, true)) {
                continue;
            }

            /*
             * ⚠️ A NUMBER WORD IS NEVER EXCUSED AS GRAMMAR. It has to appear in
             * what the member wrote, in one form or the other.
             */
            if (in_array($word, self::NUMBER_WORDS, true) && ! in_array($word, $known, true)) {
                $offences[] = $word;

                continue;
            }

            if (! in_array($this->stem($word), $known, true)) {
                $offences[] = $word;
            }
        }

        return array_values(array_unique($offences));
    }

    /**
     * ⚠️ THE OTHER HALF: WHAT THE REWRITE THREW AWAY.
     *
     * "Never invent" and "never delete" are different promises and the first
     * does not imply the second. A model that quietly drops "I have two
     * children" has changed the profile a stranger will read just as much as
     * one that adds it, and a word count cannot see it: the same model pads
     * elsewhere and the total is unchanged.
     *
     * ⚠️ ONLY FACTS ARE REQUIRED TO SURVIVE, not every word. Removing a
     * repeated "really" is a tidy-up. Removing a child is not.
     *
     * @return list<string>
     */
    public function droppedFacts(string $original, string $rewrite): array
    {
        $kept = $this->stems($rewrite);
        $lost = [];

        foreach ($this->words($original) as $word) {
            if (in_array($word, self::GRAMMAR, true)) {
                continue;
            }

            $isFact = in_array($word, self::NUMBER_WORDS, true)
                || preg_match('/^\d+$/', $word) === 1
                || preg_match('/^[a-z]{4,}$/', $word) === 1;

            if (! $isFact) {
                continue;
            }

            if (! in_array($this->stem($word), $kept, true)) {
                $lost[] = $word;
            }
        }

        return array_values(array_unique($lost));
    }

    public function isFaithful(string $original, string $rewrite): bool
    {
        return $this->unsupportedClaims($original, $rewrite) === []
            && $this->droppedFacts($original, $rewrite) === [];
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $text = strtolower($text);

        // Contractions become the words they stand for, so "I'm" is not a
        // novel token and "don't" does not read as an invented word.
        $text = str_replace(
            ["'m", "’m", "'re", "’re", "'ve", "’ve", "'ll", "’ll", "n't", "n’t", "'s", "’s"],
            [' am', ' am', ' are', ' are', ' have', ' have', ' will', ' will', ' not', ' not', ' ', ' '],
            $text,
        );

        preg_match_all('/[a-z0-9]+/', $text, $m);

        return $m[0];
    }

    /** @return list<string> */
    private function stems(string $text): array
    {
        return array_values(array_unique(array_map(
            fn (string $w) => $this->stem($w),
            $this->words($text),
        )));
    }

    /**
     * ⚠️ ENOUGH STEMMING TO ALLOW A CORRECTION, NOT ENOUGH TO ALLOW A CLAIM.
     *
     * A grammar fix turns "kid" into "kids" and "cook" into "cooking", and
     * refusing those would reject every legitimate rewrite and quietly kill the
     * feature. It does not turn "cooking" into "hiking", which is the thing
     * being guarded against.
     */
    private function stem(string $word): string
    {
        $irregular = [
            'children' => 'child', 'men' => 'man', 'women' => 'woman',
            'people' => 'person', 'feet' => 'foot', 'teeth' => 'tooth',
        ];

        if (isset($irregular[$word])) {
            return $irregular[$word];
        }

        /*
         * ⚠️ ORDER AND CONDITIONS MATTER MORE THAN THE LIST DOES.
         *
         * Stripping "es" from "likes" gives "lik", while "like" stays "like",
         * so the commonest correction of all — "i like" becoming "she likes" —
         * was being refused as an invented word. A guard that rejects correct
         * grammar teaches somebody to stop pressing the button.
         *
         * "es" is only a plural ending after a sibilant (church/churches). Any
         * other trailing "s" is the simple plural or third person.
         */
        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            return substr($word, 0, -3).'y';
        }

        if (preg_match('/(s|x|z|ch|sh)es$/', $word) === 1 && strlen($word) > 4) {
            return substr($word, 0, -2);
        }

        if (str_ends_with($word, 's') && ! str_ends_with($word, 'ss') && strlen($word) > 3) {
            return substr($word, 0, -1);
        }

        foreach (['ing', 'ed'] as $suffix) {
            if (strlen($word) > strlen($suffix) + 2 && str_ends_with($word, $suffix)) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }
}
