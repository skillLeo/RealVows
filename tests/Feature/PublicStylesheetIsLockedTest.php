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
     * ⚠️ HUE WAS THE RIGHT CORRECTION AND IT LEFT THREE DOORS OPEN.
     *
     * Matching hues rather than strings stopped a cooler white arriving from
     * the reference. It did not stop seven brass literals at seven different
     * alphas, because every one of them is the blessed hue. It did not stop
     * `color: #000` on body text, because a mask needs black and the exemption
     * was granted everywhere. And it could not see `color-mix()` at all, which
     * is a bypass that was sitting on the very line that repaired the guard.
     *
     * ⚠️ A TOKEN IS NAMED FOR THE JOB IT DOES, NOT THE NUMBER IT HOLDS. Forcing
     * a hover fill, a gradient endpoint and a modal scrim into the two hairline
     * tokens would conflate a border with a fill. Three named alpha
     * derivatives exist for exactly that reason and the alpha axis is now
     * closed to anything else.
     */
    public function test_no_colour_is_written_outside_the_token_set(): void
    {
        $css = $this->declarations();

        $allowed = $this->tokenTriplets();
        $alphas = $this->tokenAlphas();
        $offences = [];

        $outside = preg_replace('/:root[^{]*\{.*?\}/s', '', $css) ?? $css;

        preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\([^)]*\)/', $outside, $found, PREG_OFFSET_CAPTURE);

        $this->assertNotEmpty($allowed, 'No tokens were extracted from :root.');
        $this->assertGreaterThan(5, count($allowed), 'The palette extraction is reading the wrong thing.');
        $this->assertNotEmpty($found[0], 'Not one colour literal was found outside :root.');

        foreach ($found[0] as [$literal, $at]) {
            $triplet = $this->triplet($literal);

            if ($triplet === null) {
                continue;
            }

            $isMonochrome = $triplet === [0, 0, 0] || $triplet === [255, 255, 255];

            /*
             * ⚠️ BLACK AND WHITE ARE A MASK CHANNEL, NOT PAINT, AND ONLY THERE.
             *
             * A mask reads black as "hide" and white as "show". As a `color`
             * they are a colour this brand does not have, and the previous
             * exemption granted them everywhere.
             */
            if ($isMonochrome) {
                if (! $this->insideMaskContext($outside, $at)) {
                    $offences[] = "{$literal} (monochrome outside a mask)";
                }

                continue;
            }

            if (! in_array($triplet, $allowed, true)) {
                $offences[] = "{$literal} (hue not in the palette)";

                continue;
            }

            /*
             * ⚠️ AND THE ALPHA MUST BE ONE A TOKEN NAMES. Blessing two hairline
             * alphas is not blessing the axis.
             */
            $alpha = $this->alpha($literal);

            if ($alpha !== null && ! in_array($alpha, $alphas, true)) {
                $offences[] = "{$literal} (alpha {$alpha} is not named by any token)";
            }
        }

        $this->assertSame(
            [],
            $offences,
            "Colour written outside the named set:\n  ".implode("\n  ", $offences)
                ."\n\nA token is named for the job it does. If a value needs a name, name it "
                .'rather than leaving it as a literal.',
        );
    }

    /**
     * ⚠️ color-mix() PRODUCES COLOUR AND CONTAINS NO LITERAL, SO THE READER
     * ABOVE CANNOT SEE IT.
     *
     * Every input must be a token. That is the door being watched: a literal
     * entering through a function the colour reader is blind to.
     */
    public function test_every_colour_mix_takes_only_tokens(): void
    {
        $outside = preg_replace('/:root[^{]*\{.*?\}/s', '', $this->declarations()) ?? '';

        preg_match_all('/color-mix\((?:[^()]|\([^()]*\))*\)/', $outside, $mixes);

        $this->assertNotEmpty(
            $mixes[0],
            'No color-mix call was found, so this check is reading nothing. It is used across '
                .'the tint system and must be visible here.',
        );

        $offences = [];

        foreach (array_unique($mixes[0]) as $mix) {
            // Strip the interpolation space and the percentages, then anything
            // left that is not a var() or `transparent` is a literal.
            $args = preg_replace('/^color-mix\(\s*in\s+\w+\s*,?/', '', $mix) ?? $mix;
            $args = preg_replace('/var\(--[a-z0-9-]+\)|\d+%|transparent|[,()\s]/', '', $args) ?? $args;

            if (trim($args) !== '') {
                $offences[] = $mix;
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A color-mix call takes something that is not a token:\n  ".implode("\n  ", $offences)
                ."\n\nThis is the one place a literal can enter without the colour reader "
                .'seeing it.',
        );
    }

    /** ⚠️ Controls for both new axes, in both directions. */
    public function test_the_reader_distinguishes_a_mask_black_from_a_text_black(): void
    {
        $inMask = '.a { mask-image: linear-gradient(#000 0 0); }';
        $inText = '.a { color: #000; }';

        $this->assertTrue(
            $this->insideMaskContext($inMask, strpos($inMask, '#000')),
            'Black inside a mask was refused. Masks need it and it is not paint there.',
        );

        $this->assertFalse(
            $this->insideMaskContext($inText, strpos($inText, '#000')),
            'Black as a text colour was excused as a mask. That is the exemption granted '
                .'everywhere, which is what this replaced.',
        );
    }

    public function test_the_reader_catches_an_unnamed_alpha(): void
    {
        $named = $this->tokenAlphas();

        $this->assertContains(0.4, $named, 'The hairline alpha is not named by any token.');
        $this->assertContains(0.14, $named, 'The wash alpha is not named by any token.');
        $this->assertNotContains(0.77, $named, 'An alpha nobody named is being treated as named.');
    }

    /**
     * Is this offset inside a mask declaration or an SVG mask element?
     *
     * ⚠️ THE DECLARATION IT SITS IN, NOT THE WHOLE RULE. A rule can set both a
     * mask and a colour, and asking about the rule would excuse the colour.
     */
    private function insideMaskContext(string $css, int $offset): bool
    {
        $from = strrpos(substr($css, 0, $offset), ';');
        $from = $from === false ? strrpos(substr($css, 0, $offset), '{') : $from;
        $declaration = substr($css, $from === false ? 0 : $from, $offset - ($from === false ? 0 : $from));

        foreach (['mask', 'mask-image', '-webkit-mask', '-webkit-mask-image', 'clip-path'] as $property) {
            if (str_contains($declaration, $property)) {
                return true;
            }
        }

        return false;
    }

    /** The alphas the tokens actually name. @return list<float> */
    private function tokenAlphas(): array
    {
        preg_match_all('/:root[^{]*\{(.*?)\}/s', $this->declarations(), $blocks);

        $alphas = [1.0];

        preg_match_all('/rgba?\([^)]*\)/', implode(' ', $blocks[1] ?? []), $found);

        foreach ($found[0] as $literal) {
            $a = $this->alpha($literal);

            if ($a !== null && ! in_array($a, $alphas, true)) {
                $alphas[] = $a;
            }
        }

        return $alphas;
    }

    private function alpha(string $literal): ?float
    {
        if (! preg_match('/rgba\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*,\s*([\d.]+)\s*\)/', $literal, $m)) {
            return null;
        }

        return (float) $m[1];
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

    /**
     * ⚠️ THREE FACES NOW, AND THE THIRD WAS ADDED ON THE CLIENT'S INSTRUCTION
     * ON 2026-08-25. THE LOCK ITSELF IS NOT WEAKENED.
     *
     * The rule was two faces. It is now exactly three, and the reason is
     * specific rather than a loosening: his reference declares
     * --font-display: "Playfair Display" and we were setting every display line
     * in Fraunces. Both are high-contrast serifs, which is why he could see the
     * difference for weeks without being able to name it, but Playfair is a
     * Didone with flat unbracketed serifs and vertical stress and Fraunces is a
     * soft old-style face with a wonk axis. They do not read as one design.
     *
     * ⚠️ FRAUNCES DID NOT LEAVE. The authenticated product still sets it, and
     * only --display-public moves. Removing it would restyle every screen a
     * member sees, which nobody asked for.
     *
     * ⚠️ A FOURTH IS STILL REFUSED, and that is the whole point of keeping this
     * as a closed list rather than deleting it. "The palette is negotiable" is
     * what a list like this exists to stop, so the next face needs its own
     * conversation and its own line here.
     */
    public function test_only_the_three_system_faces_are_ever_requested(): void
    {
        $css = $this->declarations();

        preg_match_all('/@font-face\s*\{(.*?)\}/s', $css, $faces);

        $this->assertNotEmpty($faces[1] ?? [], 'No @font-face block was read, so this proves nothing.');

        foreach ($faces[1] as $face) {
            preg_match('/font-family:\s*[\'"]?([^;\'"]+)/', $face, $name);
            $family = trim($name[1] ?? '');

            $this->assertContains(
                $family,
                ['Fraunces', 'Inter', 'Playfair Display'],
                "A fourth typeface is being loaded: {$family}. Three faces are the system.",
            );
        }

        /*
         * ⚠️ INVERTED. ENUMERATING KNOWN FONT HOSTS FAILS ON THE FIFTH, AND THE
         * FIFTH IS ALWAYS THE ONE THAT SHIPS.
         *
         * This listed four CDNs. Anything else — a new provider, a self-hosted
         * mirror, a tracking pixel wearing a stylesheet — passed. See
         * test_no_public_page_requests_anything_from_another_origin, which asks
         * the opposite question.
         */
        $this->assertStringNotContainsString('@import', $this->declarations());
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
    /**
     * ⚠️ box-shadow IS PERMITTED HERE NOW, VIA TOKEN ONLY — R10-B §1.
     *
     * The public pages may carry the reference's three elevations as
     * var(--lift-1|2|3). Everything else in the family stays banned outright:
     * text-shadow and drop-shadow appear nowhere in the reference, and
     * "elevation" as a property name is not a thing we write.
     *
     * StylesheetIntegrityTest holds the other half — that no fourth value
     * exists and that no shadow appears outside the public scope.
     */
    public function test_no_shadow_appears_except_the_three_permitted_lifts(): void
    {
        $css = $this->declarations();

        $offences = [];

        foreach (['text-shadow', 'drop-shadow', 'elevation'] as $banned) {
            if (str_contains($css, $banned)) {
                $offences[] = $banned;
            }
        }

        preg_match_all('/box-shadow:\s*([^;}]+)/', $css, $m);

        foreach ($m[1] as $value) {
            $v = trim($value);

            if (str_contains($v, 'none') || preg_match('/^var\(--lift-[123]\)$/', $v)) {
                continue;
            }

            $offences[] = "box-shadow: {$v}";
        }

        $this->assertSame(
            [],
            $offences,
            'Only var(--lift-1|2|3) may carry elevation here: '.implode(', ', $offences)
                .'. Everything else is a tone step and a hairline weight.',
        );
    }

    /** ⚠️ CONTROL, BOTH DIRECTIONS. */
    public function test_the_shadow_reader_accepts_a_lift_and_catches_a_literal(): void
    {
        $ok = fn (string $v) => str_contains($v, 'none') || (bool) preg_match('/^var\(--lift-[123]\)$/', trim($v));

        $this->assertTrue($ok('var(--lift-1)'), 'A permitted lift was rejected.');
        $this->assertFalse($ok('0 1px 2px rgba(0,0,0,.1)'), 'A literal shadow was accepted.');
        $this->assertFalse($ok('var(--lift-4)'), 'A fourth lift token was accepted.');
    }
}
