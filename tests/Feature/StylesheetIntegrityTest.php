<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Things a stylesheet can get wrong silently.
 *
 * CSS has no collision warning and no unused-symbol warning. Every failure
 * below is one the browser accepts happily while doing the wrong thing.
 */
class StylesheetIntegrityTest extends TestCase
{
    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /**
     * ⚠️ DUPLICATE KEYFRAME NAMES SILENTLY REPLACE EACH OTHER.
     *
     * This happened: `rv-photo-settle` already animated the slow zoom on the
     * hero images, and a second definition of the same name was added for the
     * hero arrival. The later one simply won, the original stopped behaving,
     * and nothing anywhere reported a problem — it was found by rendering the
     * page and measuring it.
     */
    public function test_no_keyframe_name_is_defined_twice(): void
    {
        preg_match_all('/@keyframes\s+([\w-]+)/', $this->css(), $m);

        $counts = array_count_values($m[1]);
        $duplicates = array_filter($counts, fn ($n) => $n > 1);

        $this->assertSame(
            [],
            $duplicates,
            'These keyframe names are defined more than once, so the later definition '
                .'silently replaces the earlier one: '.implode(', ', array_keys($duplicates))
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. A regex that has stopped matching would report no
     * duplicates forever.
     */
    public function test_the_keyframe_scan_actually_finds_keyframes(): void
    {
        preg_match_all('/@keyframes\s+([\w-]+)/', $this->css(), $m);

        $this->assertGreaterThan(10, count($m[1]), 'The keyframe scan is not matching anything.');
        $this->assertContains('rv-arrive', $m[1]);

        // And that it would flag a genuine duplicate.
        $synthetic = "@keyframes rv-x { from { opacity: 0 } }\n@keyframes rv-x { to { opacity: 1 } }";
        preg_match_all('/@keyframes\s+([\w-]+)/', $synthetic, $s);
        $this->assertSame(['rv-x', 'rv-x'], $s[1]);
    }

    /**
     * Every animation must reference a keyframe that exists. A typo produces
     * an element that simply never animates, with no error.
     */
    public function test_every_animation_references_a_defined_keyframe(): void
    {
        $css = $this->css();

        preg_match_all('/@keyframes\s+([\w-]+)/', $css, $defined);
        $defined = array_unique($defined[1]);

        // `animation: NAME duration ...` — the name is the first token that is
        // not a duration, easing keyword or global value.
        preg_match_all('/animation:\s*([\w-]+)/', $css, $used);

        $ignore = ['none', 'inherit', 'initial', 'unset', 'linear', 'ease'];
        $missing = [];

        foreach (array_unique($used[1]) as $name) {
            if (in_array($name, $ignore, true) || in_array($name, $defined, true)) {
                continue;
            }

            $missing[] = $name;
        }

        $this->assertSame(
            [],
            $missing,
            'These animations reference keyframes that do not exist, so the element '
                .'never animates and nothing reports it: '.implode(', ', $missing)
        );
    }

    /**
     * ⚠️ EXACTLY THREE SHADOWS ARE PERMITTED, AND ONLY AS TOKENS — R10-B §1.
     *
     * This system banned shadows outright, and that argument still stands
     * everywhere but the public pages: a shadow is the easiest way to make a
     * product look like every other web app, and elevation belongs to a tone
     * step and a hairline.
     *
     * The client asked for his reference's look and his reference carries three
     * box-shadow values, read out of its stylesheet. They are declared once as
     * --lift-1/2/3 and may only be used through those names.
     *
     * So a declaration passes only if it is `none` or a bare var(--lift-N).
     * A literal shadow fails even when its value is identical to a permitted
     * one, because the fourth value arrives as a literal — nobody adds a fourth
     * token, they paste a shadow.
     */
    private const PERMITTED_LIFTS = ['var(--lift-1)', 'var(--lift-2)', 'var(--lift-3)'];

    public function test_only_the_three_reference_shadows_are_used(): void
    {
        preg_match_all('/box-shadow:\s*([^;}]+)/', $this->css(), $m);

        $offences = [];

        foreach ($m[1] as $value) {
            $v = trim($value);

            if (str_contains($v, 'none') || in_array($v, self::PERMITTED_LIFTS, true)) {
                continue;
            }

            $offences[] = $v;
        }

        $this->assertSame(
            [],
            $offences,
            'Only the three reference shadows are permitted, and only as var(--lift-1|2|3). '
                .'Elevation everywhere else is hairline weight. Found: '.implode(' | ', $offences)
        );
    }

    /**
     * ⚠️ AND THEY MAY ONLY APPEAR ON PUBLIC ROUTES — R10-B §1.
     *
     * The reference's elevations were adopted for the marketing pages the
     * client asked us to match. The authenticated product keeps the hairline
     * rule, so a lift used on a dashboard card fails here even though the token
     * itself is legitimate.
     *
     * Scope is proved from the selector: every rule carrying a box-shadow must
     * be written under .rv-public, which is the class the public layout puts on
     * <body> and nothing else does.
     */
    public function test_shadows_appear_only_under_the_public_scope(): void
    {
        $offences = [];

        // Each rule as `selector { declarations }`, ignoring at-rule preludes.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->css(), $rules, PREG_SET_ORDER);

        foreach ($rules as [, $selector, $body]) {
            if (! preg_match('/box-shadow:\s*(?!none)/', $body)) {
                continue;
            }

            if (! str_contains($selector, '.rv-public')) {
                $offences[] = trim(preg_replace('/\s+/', ' ', $selector));
            }
        }

        $this->assertSame(
            [],
            $offences,
            'A shadow was declared outside the public scope. Only .rv-public may carry the '
                .'reference\'s elevations; everywhere else elevation is hairline weight: '
                .implode(' | ', $offences)
        );
    }

    /** ⚠️ CONTROL for the scope rule, both directions. */
    public function test_the_scope_reader_separates_public_from_the_rest(): void
    {
        $scoped = fn (string $sel) => str_contains($sel, '.rv-public');

        $this->assertTrue($scoped('.rv-public .rv-package'), 'A public-scoped selector was rejected.');
        $this->assertFalse($scoped('.rv-match-card'), 'An authenticated selector was accepted.');
    }

    /** ⚠️ The three tokens must actually exist, or the rule above passes vacuously. */
    public function test_the_three_lift_tokens_are_declared(): void
    {
        foreach (['--lift-1', '--lift-2', '--lift-3'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*[^;]+;/',
                $this->css(),
                "{$token} is used but never declared."
            );
        }
    }

    /**
     * ⚠️ CONTROL, BOTH DIRECTIONS. A guard nobody has watched fail is a guard
     * nobody knows is wired up.
     */
    public function test_the_shadow_reader_accepts_a_token_and_rejects_a_literal(): void
    {
        $accept = fn (string $v) => str_contains($v, 'none') || in_array(trim($v), self::PERMITTED_LIFTS, true);

        $this->assertTrue($accept('var(--lift-2)'), 'A permitted token was rejected.');
        $this->assertTrue($accept('none'), 'box-shadow: none was rejected.');
        $this->assertFalse($accept('0 4px 20px rgba(26,26,26,.039)'), 'A literal copy of a permitted shadow was accepted.');
        $this->assertFalse($accept('0 2px 9px rgba(0,0,0,.3)'), 'A fourth shadow value was accepted.');
    }

    /**
     * Colour must come from the token block. A raw hex outside `:root` means
     * somebody invented a colour, which is how a locked palette drifts.
     */
    public function test_no_raw_hex_colour_outside_the_token_block(): void
    {
        $css = $this->css();

        // Everything after the :root block closes.
        $rootEnd = strpos($css, '}', strpos($css, ':root {'));
        $body = substr($css, $rootEnd);

        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $body, $m);

        $allowed = ['#000', '#fff', '#ffffff', '#000000'];
        $offences = array_values(array_filter(
            array_unique($m[0]),
            fn ($hex) => ! in_array(strtolower($hex), $allowed, true)
        ));

        $this->assertSame(
            [],
            $offences,
            'Raw hex colours found outside the token block. Every colour comes from a '
                .'custom property: '.implode(', ', $offences)
        );
    }
}
