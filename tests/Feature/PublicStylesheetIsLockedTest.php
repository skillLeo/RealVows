<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE THREE THINGS THAT MAKE A DESIGN SYSTEM STOP BEING ONE.
 *
 * A stray hex, a shadow, and a third typeface. None of them looks like damage
 * on the day it lands. Each of them arrives because somebody was matching a
 * screenshot and needed one small thing, and a year later the product has
 * forty greys and no rule at all.
 *
 * R9 rebuilt the public pages against a client reference whose palette is close
 * to ours but not ours, whose page background is a cooler white, and which has
 * elevation on one card. Every one of those is a hand reaching for a value that
 * is not in our tokens, which is exactly when a guard earns its place.
 *
 * ⚠️ THE REFERENCE'S WHITE IS THE POINT. It is cooler than our cream, and the
 * temptation is to shift the token to match. Cream is carried across the
 * authenticated product, so a public page in a different white is a seam a
 * member walks through on their way in.
 */
class PublicStylesheetIsLockedTest extends TestCase
{
    /** The one stylesheet the public pages load. */
    private function css(): string
    {
        return File::get(base_path('resources/css/app.css'));
    }

    /**
     * ⚠️ COMMENTS ARE STRIPPED FIRST. Every one of these rules is explained in
     * a comment that necessarily contains the thing it forbids, and a reader
     * that failed on its own documentation would be deleted by the first person
     * who hit it.
     */
    private function declarations(): string
    {
        return preg_replace('#/\*.*?\*/#s', '', $this->css()) ?? '';
    }

    // ── Guard 1: colour ──────────────────────────────────────────────────

    /**
     * ⚠️ A NEW HUE IS THE THING BEING GUARDED, NOT A NEW STRING.
     *
     * Three kinds of literal appear outside :root and only one of them is
     * drift:
     *
     *  · `rgba(185, 145, 84, .2)` is --brass at another alpha. Same hue, and
     *    refusing it would only push somebody to write a duplicate token.
     *  · `rgb(0 0 0)` inside a mask is a LUMINANCE CHANNEL, not a colour. A
     *    mask reads black as "hide" and white as "show", and neither is paint.
     *  · Anything else is a colour this brand does not have, which is exactly
     *    what happens when somebody is matching a screenshot.
     *
     * So the rule is the hue: every literal's RGB triplet must match a token,
     * or be pure black or white.
     */
    public function test_no_colour_is_written_outside_the_token_set(): void
    {
        $css = $this->declarations();

        $allowed = $this->tokenTriplets();
        $offences = [];

        // Strip the :root blocks, which are where tokens are legitimately
        // defined and therefore the one place a literal is not drift.
        $outside = preg_replace('/:root[^{]*\{.*?\}/s', '', $css) ?? $css;

        preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\([^)]*\)/', $outside, $found);

        /*
         * ⚠️ THE EXTRACTION IS PROVED BEFORE ITS RESULT IS BELIEVED.
         *
         * This whole check is an empty-array assertion. A regex that stopped
         * matching, or a strip that removed the entire stylesheet rather than
         * the :root blocks, reports a spotless palette forever — and reports it
         * in exactly the same words as a genuinely clean one.
         */
        $this->assertNotEmpty(
            $allowed,
            'No tokens were extracted from :root, so every literal below is being compared '
                .'against an empty palette.',
        );

        $this->assertGreaterThan(
            5,
            count($allowed),
            'Only '.count($allowed).' tokens were found. The palette is larger than that, so '
                .'the extraction is reading the wrong thing.',
        );

        $this->assertNotEmpty(
            $found[0],
            'Not one colour literal was found anywhere outside :root. A stylesheet this size '
                .'uses colour, so the reader is looking at nothing.',
        );

        foreach (array_unique($found[0]) as $literal) {
            $triplet = $this->triplet($literal);

            if ($triplet === null || in_array($triplet, $allowed, true)) {
                continue;
            }

            $offences[] = $literal;
        }

        $this->assertSame(
            [],
            $offences,
            "A colour outside the palette was written:\n  ".implode("\n  ", $offences)
                ."\n\nEvery hue must be a token. An alpha variant of a token is fine. If the "
                .'reference needs a colour we do not have, the answer is that we do not have it.',
        );
    }

    /**
     * The RGB triplets the palette actually contains, read from :root rather
     * than listed here, so this cannot drift from the tokens it guards.
     *
     * @return list<array{int, int, int}>
     */
    private function tokenTriplets(): array
    {
        preg_match_all('/:root[^{]*\{(.*?)\}/s', $this->declarations(), $blocks);

        $triplets = [
            [0, 0, 0],       // mask luminance: hide
            [255, 255, 255], // mask luminance: show
        ];

        preg_match_all(
            '/#[0-9a-fA-F]{3,8}\b|\brgba?\([^)]*\)/',
            implode(' ', $blocks[1] ?? []),
            $found,
        );

        foreach ($found[0] as $literal) {
            $t = $this->triplet($literal);

            if ($t !== null && ! in_array($t, $triplets, true)) {
                $triplets[] = $t;
            }
        }

        return $triplets;
    }

    /** @return array{int, int, int}|null */
    private function triplet(string $literal): ?array
    {
        if (str_starts_with($literal, '#')) {
            $hex = substr($literal, 1);

            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            if (strlen($hex) < 6) {
                return null;
            }

            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ];
        }

        if (preg_match_all('/\d+/', $literal, $n) && count($n[0]) >= 3) {
            return [(int) $n[0][0], (int) $n[0][1], (int) $n[0][2]];
        }

        return null;
    }

    /**
     * ⚠️ POSITIVE CONTROL. The assertion above is an empty-array check over a
     * regex, and a regex that had stopped matching would report a spotless
     * stylesheet forever.
     */
    public function test_the_colour_reader_catches_a_stray_value_and_passes_a_clean_one(): void
    {
        $dirty = '.rv-thing { color: #ff0000; border: 1px solid var(--hairline); }';
        $clean = '.rv-thing { color: var(--oxblood); border: 1px solid var(--hairline); }';

        preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\([^)]*\)/', $dirty, $inDirty);
        preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\([^)]*\)/', $clean, $inClean);

        $this->assertContains('#ff0000', $inDirty[0], 'The reader does not see a stray hex.');
        $this->assertSame([], $inClean[0], 'The reader objects to a stylesheet using only tokens.');
    }

    // ── Guard 2: typeface ────────────────────────────────────────────────

    public function test_only_fraunces_and_inter_are_ever_requested(): void
    {
        $css = $this->declarations();

        preg_match_all('/@font-face\s*\{(.*?)\}/s', $css, $faces);

        foreach ($faces[1] ?? [] as $face) {
            preg_match('/font-family:\s*[\'"]?([^;\'"]+)/', $face, $name);
            $family = trim($name[1] ?? '');

            $this->assertContains(
                $family,
                ['Fraunces', 'Inter'],
                "A third typeface is being loaded: {$family}. Two faces are the system.",
            );
        }

        /*
         * ⚠️ AND NOTHING MAY BE FETCHED FROM A FONT HOST. A stylesheet link to
         * a CDN is a third typeface that never appears in @font-face, and it is
         * also a request to somebody else's server on every page load.
         */
        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit', 'fonts.bunny.net'] as $host) {
            $this->assertStringNotContainsString($host, $this->css());
        }
    }

    public function test_the_typeface_reader_is_reading_something(): void
    {
        // ⚠️ Control: prove @font-face blocks are actually found, or the loop
        // above iterates nothing and passes for a stylesheet full of faces.
        preg_match_all('/@font-face\s*\{/', $this->css(), $faces);

        $this->assertGreaterThan(
            0,
            count($faces[0]),
            'No @font-face block was found at all, so the family check above ran over nothing.',
        );
    }

    // ── Guard 3: elevation ───────────────────────────────────────────────

    /**
     * ⚠️ ELEVATION IS TONE AND HAIRLINE, AND THAT IS A DECISION RATHER THAN A
     * LIMITATION.
     *
     * A shadow is how a card says "I am above the page". Ours says it with a
     * fill against a different fill and a border one step heavier than its
     * neighbours, which is quieter and does not date.
     */
    public function test_no_shadow_of_any_kind_appears(): void
    {
        $css = $this->declarations();

        $offences = [];

        foreach (['box-shadow', 'text-shadow', 'drop-shadow', 'elevation'] as $banned) {
            if (str_contains($css, $banned)) {
                $offences[] = $banned;
            }
        }

        $this->assertSame(
            [],
            $offences,
            'A shadow property appeared: '.implode(', ', $offences)
                .'. Prominence here is a tone step and a hairline weight, never a shadow.',
        );
    }

    public function test_the_shadow_reader_catches_one(): void
    {
        $fixture = '.rv-card { box-shadow: 0 1px 2px rgba(0,0,0,.1); }';

        $this->assertStringContainsString('box-shadow', $fixture);
        $this->assertStringNotContainsString('box-shadow', '.rv-card { border: 1px solid var(--hairline); }');
    }
}
