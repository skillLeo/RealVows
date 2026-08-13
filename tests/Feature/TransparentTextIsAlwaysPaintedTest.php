<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Text made transparent for background-clip must always have paint behind it.
 *
 * ⚠️ THIS EXISTS BECAUSE THE HOME PAGE HEADLINE DISAPPEARED IN PRODUCTION CSS.
 *
 * `background-clip: text` renders a background and clips it to the glyphs,
 * which requires the text itself to be transparent. The consequence nobody
 * pictures: if the background paints nothing, the text is not degraded, it is
 * gone. There is no fallback colour to fall back to.
 *
 * That is exactly what shipped. The gleam sweeps background-position to 180%
 * and -80%; with background-size 300% those place the gradient at [-3.6W,
 * -0.6W] and [1.6W, 4.6W], both fully outside the box. Combined with
 * `background-repeat: no-repeat` the result was nothing painted, so
 * "Find your true soul mate." vanished 2.6 seconds after every page load.
 *
 * An @supports guard was already in place and did not help, because it answers
 * a different question: whether the browser CAN clip a background to text. It
 * could. It clipped a background that was not there.
 *
 * So the invariant is narrow and mechanical: any rule that makes text
 * transparent in order to clip a background to it must let that background
 * tile. A tiling background cannot be positioned out of view.
 */
class TransparentTextIsAlwaysPaintedTest extends TestCase
{
    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /**
     * Every declaration block in the stylesheet, as raw text.
     *
     * @return list<string>
     */
    private function blocks(string $css): array
    {
        // Comments carry example code and prose about the very properties
        // being searched for, so they are removed before matching.
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/\{([^{}]*)\}/', (string) $css, $matches);

        return $matches[1];
    }

    public function test_text_clipped_to_a_background_never_uses_no_repeat(): void
    {
        $blocks = $this->blocks($this->css());

        /*
         * ⚠️ PROVE THE EXTRACTION FOUND THE STYLESHEET BEFORE TRUSTING WHAT IT
         * DID NOT FIND IN IT.
         *
         * Everything below is an assertion about absence, and absence is what
         * a broken reader reports too. If the comment-stripping regex or the
         * block regex ever stops matching, this file goes green while checking
         * nothing — which is the failure it was written to prevent, wearing a
         * different hat.
         */
        $this->assertNotEmpty(
            $blocks,
            'The block reader found nothing in app.css, so every result below is meaningless.'
        );

        $this->assertGreaterThan(
            500,
            count($blocks),
            'The block reader found almost nothing in app.css. Not empty is not the same as working.'
        );

        $this->assertTrue(
            collect($blocks)->contains(fn (string $b) => str_contains($b, 'background-clip: text')),
            'No rule clips a background to text any more. Either the gleam was removed — in which case delete this guard — or the reader has stopped seeing it.'
        );

        $offenders = [];

        foreach ($blocks as $block) {
            $clipsToText = str_contains($block, 'background-clip: text');
            $hidesText = str_contains($block, 'color: transparent')
                || str_contains($block, 'text-fill-color: transparent');

            if ($clipsToText && $hidesText && str_contains($block, 'background-repeat: no-repeat')) {
                $offenders[] = trim(preg_replace('/\s+/', ' ', $block));
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'A rule makes text transparent to clip a background to it, and stops that',
            'background repeating. If the background is ever positioned outside the box',
            'the text is invisible, not merely unstyled. Let it tile.',
        ]));
    }

    public function test_the_reader_can_still_see_the_fault_it_was_written_for(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL. Without it this file passes forever the moment
         * the matching string changes — including if somebody reformats the
         * stylesheet so `background-clip:text` has no space after the colon.
         * A guard that cannot fail has already failed.
         */
        $broken = <<<'CSS'
        .rv-hero .rv-d1 {
          background-image: linear-gradient(100deg, #fff 42%, #D4AF6A 50%, #fff 58%);
          background-size: 300% 100%;
          background-repeat: no-repeat;
          background-clip: text;
          color: transparent;
        }
        CSS;

        $offenders = [];

        foreach ($this->blocks($broken) as $block) {
            if (
                str_contains($block, 'background-clip: text')
                && str_contains($block, 'color: transparent')
                && str_contains($block, 'background-repeat: no-repeat')
            ) {
                $offenders[] = $block;
            }
        }

        $this->assertCount(1, $offenders, 'The reader no longer detects the exact CSS that broke the home page.');
    }

    public function test_the_reader_does_not_fire_on_ordinary_transparent_colour(): void
    {
        /*
         * The other direction. `color: transparent` is perfectly safe on its
         * own — it is how a decorative element hides its own text — and a
         * reader that objected to it would flag rules that are entirely
         * correct until somebody deleted the guard for being noise.
         */
        $fine = <<<'CSS'
        .rv-sr-only { color: transparent; background-repeat: no-repeat; }
        .rv-logo { background-clip: text; }
        CSS;

        $offenders = [];

        foreach ($this->blocks($fine) as $block) {
            if (
                str_contains($block, 'background-clip: text')
                && str_contains($block, 'color: transparent')
                && str_contains($block, 'background-repeat: no-repeat')
            ) {
                $offenders[] = $block;
            }
        }

        $this->assertSame([], $offenders, 'The reader fires on CSS that is not the fault.');
    }
}
