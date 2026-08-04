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
}
