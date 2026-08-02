<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE PAGE BODY MUST NEVER SCROLL SIDEWAYS.
 *
 * A real bug this catches, found by measuring rather than by looking: the
 * photograph behind the auth band animates from `scale(1.03)`, and a scaled,
 * absolutely positioned pseudo-element extends past its parent. Every public
 * page scrolled horizontally by 1.5% of the viewport — 6px on a 375px phone,
 * 22px at 1440. Invisible in a screenshot, obvious to a thumb.
 *
 * ── Why this is a source check and not a browser check ────────────────────
 *
 * The real measurement needs a browser at exact widths, and it has a trap:
 * HEADLESS CHROME CLAMPS ITS VIEWPORT TO 500px MINIMUM, so `--window-size=375`
 * captures 375 physical pixels of a 500px layout and lies convincingly. I
 * nearly "fixed" a layout that was fine because of it. The working method is
 * an iframe — an iframe 375px wide IS a 375px viewport — and it is documented
 * in DEPLOY.md for the manual audit.
 *
 * What runs on every commit is cheaper and catches the specific mistake that
 * caused the bug: a scaling pseudo-element inside an unclipped parent.
 */
class HorizontalOverflowTest extends TestCase
{
    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /**
     * Every selector whose ::before or ::after is animated with a scale must
     * clip, or the scaled box pushes the document wider than the viewport.
     */
    public function test_every_scaling_pseudo_element_lives_in_a_clipping_parent(): void
    {
        $css = $this->css();

        // Keyframes that scale up at any point.
        preg_match_all('/@keyframes\s+([\w-]+)\s*\{([^}]*\{[^}]*\}[^}]*)*\}/', $css, $blocks, PREG_SET_ORDER);

        $scaling = [];

        foreach ($blocks as $block) {
            if (preg_match('/scale\(\s*1\.\d/', $block[0])) {
                $scaling[] = $block[1];
            }
        }

        $this->assertNotEmpty($scaling, 'No scaling keyframes found — the scan has stopped matching.');

        $offences = [];

        foreach ($scaling as $keyframe) {
            // Which pseudo-element selectors use it.
            preg_match_all(
                '/([.#][\w-]+(?:--[\w-]+)?)::(?:before|after)[^{]*\{[^}]*animation:\s*'.preg_quote($keyframe, '/').'/',
                $css,
                $uses
            );

            foreach (array_unique($uses[1]) as $parent) {
                /*
                 * The element itself must clip — either through its own rule
                 * or through its BEM base class, since `.a--b` always carries
                 * `.a` in the markup and clipping the base is sufficient.
                 */
                $candidates = [$parent];

                if (str_contains($parent, '--')) {
                    $candidates[] = substr($parent, 0, strpos($parent, '--'));
                }

                $clips = false;

                foreach ($candidates as $selector) {
                    if (preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*overflow:\s*(hidden|clip)/', $css)) {
                        $clips = true;
                        break;
                    }
                }

                if (! $clips) {
                    $offences[] = "{$parent}::before/after animates {$keyframe} which scales up, "
                        ."but {$parent} does not clip. The scaled box will make the page scroll sideways.";
                }
            }
        }

        $this->assertSame([], $offences, "\n  ".implode("\n  ", $offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL. This scan is a negative assertion over regexes, so
     * it is worthless unless it can see a real offence.
     */
    public function test_the_scan_can_actually_detect_an_unclipped_scaling_pseudo_element(): void
    {
        $css = $this->css();

        // The rules the fix added must be present and findable.
        $this->assertMatchesRegularExpression(
            '/\.rv-auth__band--photo\s*\{[^}]*overflow:\s*hidden/',
            $css,
            'The auth band no longer clips, so the overflow bug is back.'
        );

        $this->assertMatchesRegularExpression(
            '/\.rv-split__panel\s*\{[^}]*overflow:\s*hidden/',
            $css,
            'The split panel no longer clips.'
        );

        // And that a scaling keyframe is actually detected.
        $this->assertMatchesRegularExpression('/@keyframes\s+rv-settle-photo/', $css);
        $this->assertStringContainsString('scale(1.03)', $css);
    }

    /**
     * `100vw` includes the scrollbar on desktop, so a full-bleed element sized
     * with it overflows by the scrollbar width on every page that scrolls.
     * Permitted only inside a `calc()` that subtracts a gutter.
     */
    public function test_no_bare_full_viewport_width(): void
    {
        preg_match_all('/[\w-]+:\s*[^;{}]*\b100vw\b[^;{}]*/', $this->css(), $m);

        $bare = array_values(array_filter(
            $m[0],
            fn ($decl) => ! str_contains($decl, 'calc(') && ! str_contains($decl, 'min(')
        ));

        $this->assertSame(
            [],
            $bare,
            '100vw includes the scrollbar and overflows on any scrolling page. '
                .'Use calc(100vw - …) or a percentage: '.implode(' | ', $bare)
        );
    }
}
