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
     * ⚠️ The design system bans shadows outright — elevation is carried by
     * hairline weight. A shadow is the single easiest way to make this product
     * look like every other web app.
     */
    public function test_no_box_shadow_has_crept_in(): void
    {
        preg_match_all('/box-shadow:\s*([^;}]+)/', $this->css(), $m);

        $real = array_filter($m[1], fn ($v) => ! str_contains($v, 'none'));

        $this->assertSame(
            [],
            array_values($real),
            'box-shadow is banned in this design system. Elevation is hairline weight: '
                .implode(' | ', $real)
        );
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
