<?php

namespace Tests\Support;

/**
 * ⚠️ EVERY READER THAT A GUARD USES TO LOOK FOR A VIOLATION LIVES HERE.
 *
 * Six times now this project has shipped something that reported success while
 * checking nothing: a JSON search defeated by slash escaping, a self-built
 * signed URL, a queued-mailable assertion, an overflow guard blinded by a
 * refactor, two measurement harnesses, and — worst — a zodiac grep that
 * printed CLEAN while scanning zero files.
 *
 * Every one of them was a READER that stopped reading. The guard around it was
 * fine. So the readers are collected here, where a single meta-test can plant
 * a known violation in front of each one and confirm it still sees it.
 *
 * ⚠️ A GUARD MUST NOT DECLARE ITS OWN PRIVATE COPY OF A READER. A regex inlined
 * in a test method cannot be exercised by the meta-test, so it is exactly the
 * kind of reader that goes quietly blind. PositiveControlsAreAliveTest fails
 * the build if a guard re-declares one of these patterns locally.
 */
class Scanners
{
    /**
     * The affinity vocabulary, banned from every surface a member can reach.
     *
     * ⚠️ SIGN NAMES ARE MATCHED AS WHOLE WORDS. A substring search flags
     * "skillleo" — the developer's own hostname — as a star sign, and the
     * tempting fix for that is an exception list, which switches the guard off
     * for whatever else the exempted string later contains.
     */
    public const SIGN_NAMES = [
        'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
        'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces',
    ];

    public const PHRASES = [
        'zodiac', 'star sign', 'sun sign', 'astrolog', 'synastry', 'horoscope',
        'natal', 'birth chart', 'celestial',
    ];

    public const GLYPHS = ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'];

    /**
     * ⚠️ PERMITTED IN MARKETING PROSE. BANNED IN GENERATED MATCH COPY.
     *
     * This is the only term in this file with a different answer depending on
     * where it appears, so the reasoning has to be written down — the next
     * person will see "synergy" on the home page, assume it is approved, and
     * put it in a rationale.
     *
     * The client wrote the word himself: "Their synergy will help them develop
     * that bond and a lasting relationship." In a marketing paragraph it is a
     * warm generic word about two people fitting, and it is his, so it stays.
     *
     * In the sentence explaining WHY THIS PARTICULAR PERSON WAS CHOSEN FOR YOU
     * it stops being generic and starts reading as the name of a mechanism —
     * and it is one letter-cluster from `synastry`, which is the actual term
     * for the thing the whole zodiac constraint exists to conceal. A member who
     * reads "your synergy" on a match screen is one search away from it.
     *
     * ⚠️ DO NOT FOLD THIS INTO PHRASES, and do not widen either list toward a
     * `syn` prefix — that bans the client's own sentence on his own home page.
     */
    public const MECHANISM_ADJACENT = ['synergy', 'synergies', 'synergistic'];

    /**
     * Affinity vocabulary found in a piece of text.
     *
     * ⚠️ "cancer" and "leo" are in SIGN_NAMES and are also ordinary English.
     * Whole-word matching is what makes that tolerable; the caller decides
     * whether a whole-word "cancer" in prose is a false positive worth an
     * argument. It is never silently dropped here.
     *
     * @return list<string>
     */
    public static function forbiddenTerms(string $text): array
    {
        $found = [];

        foreach (self::PHRASES as $phrase) {
            if (stripos($text, $phrase) !== false) {
                $found[] = $phrase;
            }
        }

        foreach (self::GLYPHS as $glyph) {
            if (str_contains($text, $glyph)) {
                $found[] = $glyph;
            }
        }

        $pattern = '/\b('.implode('|', self::SIGN_NAMES).')\b/i';

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $found[] = strtolower($match);
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Text that is the entire content of an element and reads like a sentence.
     *
     * This is the copy-deck reader: what a member reads, as opposed to a class
     * name, an icon key or an interpolation.
     *
     * @return list<string>
     */
    public static function rawCopyStrings(string $template): array
    {
        preg_match_all('/>\s*([A-Z][^<>{}]{14,})\s*</', $template, $matches);

        return array_values(array_filter(
            array_map('trim', $matches[1]),
            fn (string $text) => str_word_count($text) >= 3,
        ));
    }

    /**
     * Anything that resolves to a photograph route.
     *
     * ⚠️ THE SLASHES MATTER. json_encode escapes them, so a search for
     * "/photos/" against an encoded payload finds nothing — which is precisely
     * how this project's first vacuous guard passed. Both spellings are
     * checked so the encoding cannot hide a leak.
     *
     * @return list<string>
     */
    public static function photoPaths(string $payload): array
    {
        preg_match_all('#(\\\\/photos\\\\/|/photos/)[^"\\\\\s]*#', $payload, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * A company name hardcoded where the billing entity should be read from
     * settings.
     *
     * @return list<string>
     */
    public static function hardcodedEntities(string $text, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            if (stripos($text, $name) !== false) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * Numbered placeholders in a WhatsApp template body, in ascending order.
     *
     * @return list<int>
     */
    public static function templatePlaceholders(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $numbers = array_values(array_unique(array_map('intval', $matches[1])));
        sort($numbers);

        return $numbers;
    }

    /**
     * ⚠️ THE WORD IS NOT BANNED. LEADING WITH IT IS.
     *
     * From the client: "the word you want to use is not marriage, because
     * marriage frightens a lot of people. It is more about finding your soul
     * mate." He did not ban it — his own approved sentence ends "…and a lasting
     * relationship, leading to marriage."
     *
     * So the constraint is positional and contextual, not lexical: marriage is
     * the destination, named late and softly, in a small number of places that
     * are listed and justified. MarriageLeadTest owns that list.
     */
    public const MARRIAGE_FORWARD = [
        'marriage', 'marriages', 'marry', 'marries', 'marrying', 'married',

        /*
         * ⚠️ THE REST OF THE REGISTER, ADDED BEFORE THE COPY REWRITE RATHER
         * THAN AFTER IT.
         *
         * The first version of this list read for the word "marriage" and its
         * inflections and nothing else. That is the false-pass shape this
         * project keeps hitting: a reader that catches one spelling of the
         * thing and reports clean while the thing itself walks past.
         *
         * The client's objection was never to a single word. He said the word
         * marriage frightens people and asked for soul mate instead. "Wife",
         * "wedding" and "tie the knot" carry identical weight and land on the
         * identical instinct. A guard blind to them is a guard that lets the
         * old framing back in through a side door, and a full copy rewrite is
         * exactly when a writer reaches for familiar vocabulary.
         *
         * ⚠️ THIS ALREADY CAUGHT SOMETHING. "Settle down" was introduced BY the
         * previous pass, as the replacement for "hoping to marry" in
         * CommonalityFinder and MatchPresenter. It is the same register wearing
         * a softer coat, and the narrow reader had no opinion about it.
         */
        'wife', 'wives', 'husband', 'husbands', 'spouse', 'spouses', 'spousal',
        'wedding', 'weddings', 'bride', 'brides', 'bridal', 'groom', 'grooms',
        'matrimony', 'matrimonial', 'nuptial', 'nuptials', 'betrothed',
        'settle down', 'settling down', 'tie the knot', 'walk down the aisle',
        'mr and mrs', 'till death do us part', 'til death do us part',
    ];

    /**
     * ⚠️ `engagement` IS DELIBERATELY ABSENT, AND THIS NOTE IS THE REASON.
     *
     * It carries the marital sense only sometimes. In a product that also has
     * an admin panel, "engagement" is an ordinary analytics word, and a reader
     * that fires on both produces exactly the noise that trains people to
     * override guards. If the marital sense ever appears in member copy it
     * will be as "engaged to", which the reviewer reading the page will catch;
     * a machine cannot tell the two apart and should not pretend to.
     */

    /**
     * Marriage-forward vocabulary in a piece of text, whole words only.
     *
     * @return list<string>
     */
    public static function marriageForwardTerms(string $text): array
    {
        $found = [];

        foreach (self::MARRIAGE_FORWARD as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/i', $text) === 1) {
                $found[] = $term;
            }
        }

        return $found;
    }

    /**
     * What a person actually reads on a rendered page: markup, scripts, styles
     * and Blade comments removed, entities decoded, whitespace collapsed.
     *
     * ⚠️ SCRIPT AND STYLE ARE STRIPPED WITH THEIR CONTENTS, not just their
     * tags. `strip_tags` alone leaves the body of a <script> in place, so a
     * reader built on it scans minified JavaScript and reports matches nobody
     * can see — noise, which is how a guard gets switched off.
     */
    public static function visibleText(string $html): string
    {
        $withoutCode = preg_replace(
            ['#<script\b[^>]*>.*?</script>#is', '#<style\b[^>]*>.*?</style>#is', '#<!--.*?-->#s'],
            ' ',
            $html,
        );

        $text = html_entity_decode(strip_tags((string) $withoutCode), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Split prose into sentences, keeping the terminator.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /**
     * ⚠️ THE MARKS THAT MAKE PROSE READ AS MACHINE-WRITTEN.
     *
     * From the client, after reading the site properly for the first time:
     * "the people who read this, who will be serious, they will look at the
     * grammar and they would think somebody just came up with this and tried
     * to rig this up in a few days."
     *
     * He is right, and the reasoning is sharper than it looks. On a product
     * whose entire claim is that it takes this seriously, prose that reads as
     * generated undermines the proposition more than a missing feature would.
     * A reader deciding whether to hand over their photographs reads the
     * writing as evidence of care.
     *
     * The em dash is the strongest single tell and the one he named. It is
     * banned outright rather than swapped for a comma: a sentence that needs a
     * dash to hold together is two sentences.
     */
    public const AI_PUNCTUATION = ['—', '–', ';'];

    /** Vocabulary that never appears in writing a person did on purpose. */
    public const BANNED_VOCABULARY = [
        'journey', 'seamless', 'seamlessly', 'curated', 'elevate', 'elevated',
        'unlock your potential', 'tailored', 'bespoke experience', 'world-class',
        'cutting-edge', 'leverage', 'empower', 'delve', 'at the end of the day',
        "in today's world", 'more than just',
    ];

    /**
     * Contractions, for the surfaces that hold a formal register.
     *
     * ⚠️ POSSESSIVE `'s` IS NOT A CONTRACTION and must never match. The first
     * version of this reader flagged "one person's wishes" and "another's",
     * which is the over-eager half of the same failure: a guard producing
     * noise gets overridden, and then it is not a guard.
     */
    public static function contractions(string $text): array
    {
        preg_match_all(
            "/\b(?:\w+n't|\w+'(?:re|ve|ll|m)|(?:it|that|here|there|what|who|let|he|she|we|you|they|this)'s)\b/i",
            $text,
            $matches,
        );

        return array_values(array_unique($matches[0]));
    }

    /** @return list<string> */
    public static function aiPunctuation(string $text): array
    {
        return array_values(array_filter(
            self::AI_PUNCTUATION,
            fn (string $mark) => str_contains($text, $mark),
        ));
    }

    /** @return list<string> */
    public static function bannedVocabulary(string $text): array
    {
        return array_values(array_filter(
            self::BANNED_VOCABULARY,
            fn (string $word) => preg_match('/\b'.preg_quote($word, '/').'\b/i', $text) === 1,
        ));
    }

    /**
     * Mechanism-adjacent vocabulary found in generated match copy.
     *
     * ⚠️ WHOLE WORDS. `synergy` must not match inside a longer word, and the
     * list must never be reduced to a prefix — see MECHANISM_ADJACENT.
     *
     * @return list<string>
     */
    public static function mechanismAdjacentTerms(string $text): array
    {
        $found = [];

        foreach (self::MECHANISM_ADJACENT as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/i', $text) === 1) {
                $found[] = $term;
            }
        }

        return $found;
    }

    /**
     * URLs in a body, so they can be checked as hostnames rather than prose.
     *
     * @return list<string>
     */
    public static function urls(string $text): array
    {
        preg_match_all('#\bhttps?://\S+|\b[a-z0-9.-]+\.(?:com|io|app)\S*#i', $text, $matches);

        return $matches[0];
    }

    /** A body with its URLs removed — what a reader reads as language. */
    public static function prose(string $text): string
    {
        return preg_replace('#\bhttps?://\S+|\b[a-z0-9.-]+\.(?:com|io|app)\S*#i', ' ', $text);
    }

    /**
     * Every hostname a piece of rendered output points at, lowercased and
     * de-duplicated.
     *
     * ⚠️ THIS EXISTS BECAUSE THE MARKETING PAGE SHIPPED A DOMAIN WE DO NOT OWN.
     *
     * `realvows.com` was rendered inside a mock-up on the live home page for
     * weeks. It is not a typo with cosmetic consequences: a domain that appears
     * in a real product's marketing is precisely the signal a squatter watches
     * for, and the day somebody registers it, the client's own site carries a
     * link to whatever they choose to put there.
     *
     * ⚠️ IT TAKES RENDERED OUTPUT, NOT SOURCE, and that is not a preference.
     * Scanning the source of `WhatsAppTemplates.php` reports the host
     * `nrealvows.skillleo.com`, because the two characters before it in the
     * file are `\` and `n` and only PHP's parser knows that is a newline. A
     * source reader sees a hostname that does not exist and misses the one that
     * does.
     *
     * @return list<string>
     */
    public static function hosts(string $text): array
    {
        /*
         * Scheme-relative and absolute URLs, plus bare hosts written as text —
         * `realvows.skillleo.com/dashboard` in a WhatsApp body is a link the
         * moment it reaches a phone, whether or not it carries a scheme.
         */
        preg_match_all(
            '#(?:https?:)?//([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+)'
            .'|(?<![a-z0-9@.\\\\-])([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*'
            .'\.(?:com|net|org|io|co|uk|jm|dev|app|test|info|biz|me|ai|cloud|site|online|xyz|link|live))\b#i',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        $hosts = [];

        foreach ($matches as $match) {
            $host = strtolower($match[1] !== '' ? $match[1] : ($match[2] ?? ''));

            if ($host !== '') {
                $hosts[$host] = true;
            }
        }

        return array_keys($hosts);
    }
}
