<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE CATALOGUE HAD ALREADY ROTTED ONCE, AND NOBODY NOTICED.
 *
 * Fourteen primitives were specified. Twenty-six keyframes existed. Nine of
 * them — rv-arrive, rv-rise, rv-flash-in, rv-settle, rv-settle-photo,
 * rv-mosaic-in, rv-mosaic-settle, rv-hero-arrive, rv-hero-zoom — were the
 * SAME primitive, "fade up with a small transform", differing only in
 * distance. Nine names for one idea, and the only way it was found was by
 * counting them by hand.
 *
 * That is drift that looks like work: the stylesheet appears richer while
 * actually being less consistent. A comment saying "sixteen primitives"
 * cannot prevent it. This can.
 */
class MotionCatalogueTest extends TestCase
{
    /**
     * The registered keyframes, and nothing else may exist.
     *
     * ⚠️ ADDING A NAME HERE IS A DESIGN DECISION, NOT A FORMALITY. If the
     * honest answer is "this is The Arrival at a different distance", the fix
     * is `--arrive-y`, not a new entry.
     */
    private const REGISTERED = [
        // 1 · The Arrival — parameterised by --arrive-y / --arrive-scale / --arrive-fade
        'rv-arrive',
        // 2 · The Ribbon Draw
        'rv-draw',
        // 5 · The Veil
        'rv-sheen', 'rv-unwipe', 'rv-border-draw', 'rv-demo-veil-wipe',
        // 6 · The Seal Break
        'rv-seal-part-l', 'rv-seal-part-r',
        // 9 · The Morph — page transitions
        'rv-slide-out-left', 'rv-slide-in-right', 'rv-slide-out-right', 'rv-slide-in-left',
        // 11 · The Shimmer
        'rv-shimmer',
        // 14 · The Candlelight
        'rv-drift',
        // 15 · The Gleam — marketing only
        'rv-arch-gleam',
        // 16 · The Ticker — marketing only
        'rv-marquee-slide',
        // The hero mosaic's scroll-driven ambient drift, part of 14.
        'rv-mosaic-drift', 'rv-mosaic-drift-reverse',
        /*
         * "The Meeting" — the hero composition. Marketing only, and it loops on
         * the same argument as 15 and 16: a marketing page has no task for a
         * loop to compete with. Two lines converging under a keystone, roughly
         * every twenty seconds.
         */
        'rv-meet-left', 'rv-meet-right', 'rv-keystone-warm',
        /*
         * The compatibility meter's arc, R10-D §2. Marketing only and it does
         * NOT loop — it runs once, on scroll, and the finished state is what
         * remains.
         *
         * ⚠️ REGISTERED AS ITS OWN PRIMITIVE RATHER THAN FOLDED INTO #1 THE
         * ARRIVAL, and the distinction is real. The Arrival moves a whole
         * element into place; this draws one arc to a length that is different
         * for every segment and comes out of config. It cannot be expressed as
         * a transform, so reusing rv-arrive would have meant a second animation
         * on the same element fighting the first.
         */
        'rv-meter-seg',
    ];

    /**
     * ⚠️ These may loop. Nothing else may, because inside the product a loop
     * competes with whatever the member is trying to do.
     */
    private const MAY_LOOP = [
        'rv-shimmer', 'rv-drift', 'rv-arch-gleam', 'rv-marquee-slide',
        // "The Meeting". Marketing only, twenty-second cycle.
        'rv-meet-left', 'rv-meet-right', 'rv-keystone-warm',
    ];

    /** ⚠️ These may never appear on an authenticated route. */
    private const MARKETING_ONLY = [
        'rv-arch-gleam', 'rv-marquee-slide',
        'rv-meet-left', 'rv-meet-right', 'rv-keystone-warm',
    ];

    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /** @return list<string> */
    private function keyframesIn(string $css): array
    {
        preg_match_all('/@keyframes\s+([a-z0-9-]+)\s*\{/i', $css, $m);

        return array_values(array_unique($m[1]));
    }

    /** ⚠️ THE CHECK THIS CLASS EXISTS FOR. */
    public function test_every_keyframe_is_a_registered_primitive(): void
    {
        $unregistered = array_diff($this->keyframesIn($this->css()), self::REGISTERED);

        $this->assertSame(
            [],
            array_values($unregistered),
            "Unregistered keyframes: ".implode(', ', $unregistered).".\n\n"
                ."Every animation must be one of the sixteen primitives. Before adding a name to "
                .'MotionCatalogueTest::REGISTERED, check it is not an existing primitive at a '
                .'different distance — that is how nine names for The Arrival happened.'
        );
    }

    /**
     * ⚠️ And the reverse: a registered primitive that no longer exists means
     * the list is describing a stylesheet that has moved on.
     */
    public function test_every_registered_primitive_still_exists(): void
    {
        $missing = array_diff(self::REGISTERED, $this->keyframesIn($this->css()));

        $this->assertSame(
            [],
            array_values($missing),
            'Registered but absent: '.implode(', ', $missing).'. The catalogue is describing '
                .'animations that are gone.'
        );
    }

    /** Only the Shimmer, the Candlelight, the Gleam and the Ticker may loop. */
    public function test_nothing_loops_that_is_not_allowed_to(): void
    {
        preg_match_all(
            '/animation:\s*([a-z0-9-]+)[^;]*\binfinite\b/i',
            $this->css(),
            $m
        );

        $looping = array_values(array_unique($m[1]));

        foreach ($looping as $name) {
            $this->assertContains(
                $name,
                self::MAY_LOOP,
                "{$name} loops forever but is not allowed to. Inside the product a loop competes "
                    .'with whatever the member is doing — only ambient warmth and skeletons repeat.'
            );
        }
    }

    /**
     * ⚠️ THE SCOPE THAT REPLACED THE RULE.
     *
     * The Gleam and the Ticker were allowed to loop on the argument that a
     * marketing page has no task to compete with. That argument only holds
     * while they stay off the authenticated side, so the exemption is worth
     * exactly as much as this check.
     */
    public function test_marketing_only_motion_never_reaches_an_authenticated_screen(): void
    {
        $files = array_merge(
            File::allFiles(resource_path('js/Pages')),
            File::allFiles(resource_path('js/Components')),
            File::allFiles(resource_path('js/Layouts'))
        );

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            foreach (self::MARKETING_ONLY as $name) {
                $this->assertStringNotContainsString(
                    $name,
                    $contents,
                    "{$name} appears in {$file->getFilename()}, which is an authenticated screen. "
                        .'It is allowed to loop only because a marketing page has no task for it '
                        .'to compete with. On this side of the login that is no longer true.'
                );
            }

            // And the class hooks that carry them.
            foreach (['rv-marquee', 'rv-mosaic__tile--arch'] as $hook) {
                $this->assertStringNotContainsString(
                    $hook,
                    $contents,
                    "{$hook} carries marketing-only looping motion and must not appear in "
                        .$file->getFilename().'.'
                );
            }
        }
    }

    /**
     * ⚠️ The nine names must not come back. This is the specific regression,
     * named, so the failure message explains itself.
     */
    public function test_the_arrival_has_not_split_into_variants_again(): void
    {
        $css = $this->css();

        foreach (['rv-rise', 'rv-flash-in', 'rv-settle', 'rv-settle-photo',
            'rv-mosaic-in', 'rv-mosaic-settle', 'rv-hero-arrive', 'rv-hero-zoom'] as $ghost) {
            $this->assertStringNotContainsString(
                "@keyframes {$ghost}",
                $css,
                "{$ghost} is back. It is The Arrival at a different distance — use "
                    .'--arrive-y, --arrive-scale and --arrive-fade on rv-arrive instead.'
            );
        }

        // The parameters that made the collapse possible.
        foreach (['--arrive-y', '--arrive-scale', '--arrive-fade'] as $knob) {
            $this->assertStringContainsString(
                $knob,
                $css,
                "rv-arrive lost {$knob}, so it can no longer stand in for the variants it "
                    .'replaced and they will be rebuilt.'
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every check above is a scan over source text, so
     * each reader must be shown to see the thing it claims to.
     */
    public function test_the_readers_can_see_what_they_claim_to(): void
    {
        // An unregistered keyframe is caught.
        $rogue = $this->keyframesIn('@keyframes rv-totally-new { from { opacity: 0; } }');
        $this->assertSame(['rv-totally-new'], $rogue);
        $this->assertNotEmpty(array_diff($rogue, self::REGISTERED));

        // A disallowed loop is caught.
        preg_match_all(
            '/animation:\s*([a-z0-9-]+)[^;]*\binfinite\b/i',
            '.x { animation: rv-arrive 2s linear infinite; }',
            $m
        );
        $this->assertSame(['rv-arrive'], $m[1]);
        $this->assertNotContains('rv-arrive', self::MAY_LOOP);

        // A one-shot animation is NOT read as a loop.
        preg_match_all(
            '/animation:\s*([a-z0-9-]+)[^;]*\binfinite\b/i',
            '.y { animation: rv-arrive 520ms var(--ease) both; }',
            $none
        );
        $this->assertSame([], $none[1]);
    }
}
