<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE ONLY THING A STRANGER *DOES* ON THE MARKETING PAGE.
 *
 * A sealed introduction that opens when tapped. It teaches the product's whole
 * mechanic in one gesture, which means it has to keep two promises the rest of
 * the page does not: it must perform the SAME ceremony the product performs,
 * and it must not put a made-up score against a real person.
 */
class IntroductionDemoTest extends TestCase
{
    private function markup(): string
    {
        return File::get(resource_path('views/public/partials/introduction-demo.blade.php'));
    }

    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    private function js(): string
    {
        return File::get(resource_path('js/public.js'));
    }

    /**
     * ⚠️ WHAT IS ACTUALLY SERVED, not what is written.
     *
     * Blade comments never reach the browser, so a check for forbidden words
     * run against the source reads this file's own warnings about them and
     * fails on the safety rail rather than on a breach.
     */
    private function rendered(): string
    {
        return $this->get('/')->getContent();
    }

    /**
     * ⚠️ THE CEREMONY MUST BE THE PRODUCT'S, NOT A COPY OF IT.
     *
     * A second implementation drifts from the first within a release, and then
     * the marketing page is promising a moment the product no longer performs.
     * These are the product's own classes — if the demo stops using them,
     * somebody has restaged the unsealing here.
     */
    public function test_the_demo_reuses_the_products_unsealing(): void
    {
        $markup = $this->markup();

        foreach ([
            'rv-veil' => 'the veil',
            'rv-veil__sheen' => 'the brass sheen',
            'rv-seal__half' => 'the breakable seal',
            'rv-ring__arc' => 'the compatibility ring',
        ] as $class => $what) {
            $this->assertStringContainsString(
                $class,
                $markup,
                "The demo no longer uses {$what} from the product, so the page is performing a "
                    .'ceremony the dashboard does not.'
            );
        }

        /*
         * ⚠️ The photograph is the exception, and deliberately so: it is built
         * by JS on open because a sealed card renders no image element at all.
         * It still has to be the product's class, or it will not unwipe.
         */
        $this->assertStringContainsString(
            "img.className = 'rv-match__photo rv-demo__photo'",
            $this->js(),
            'The revealed photograph no longer carries the product class, so it will appear '
                .'without the unwipe the ceremony depends on.'
        );

        // And it is driven by the product's switch, not its own.
        $this->assertStringContainsString(
            "classList.add('rv-unsealing', 'is-open')",
            $this->js(),
            'The demo no longer fires the unsealing through .rv-unsealing.'
        );
    }

    /**
     * ⚠️ A SEALED INTRODUCTION MUST SHOW ITS SCORE.
     *
     * That is the tease, and it is what the product does. An earlier build
     * wound the ring back to zero on page load, so a sealed card sat there
     * reading 0 out of 100 — the opposite of what the section is for. The
     * winding belongs inside the open handler.
     */
    public function test_the_sealed_card_shows_its_score(): void
    {
        $js = $this->js();

        $openAt = strpos($js, 'const open = () => {');
        $this->assertNotFalse($openAt, 'The demo has no open handler.');

        $openBody = substr($js, $openAt, 900);

        $this->assertStringContainsString(
            'wind();',
            $openBody,
            'The ring is no longer wound back when the card opens, so it cannot draw.'
        );

        /*
         * The give-away for the regression: wind() called at the end of init,
         * outside any handler, where it runs on load.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/\n    wind\(\);\n/',
            $js,
            'wind() runs on page load again, which leaves a sealed introduction showing a score '
                .'of zero until somebody taps it.'
        );

        // The markup ships fully drawn, so a visitor without JS is never told
        // this person scores nothing.
        preg_match('/data-demo-arc[^>]*/', $this->rendered(), $arc);
        $this->assertNotEmpty($arc, 'The demo ring is not rendered.');

        preg_match('/stroke-dashoffset="([0-9.]+)"/', $this->rendered(), $offset);
        $this->assertNotEmpty(
            $offset,
            'The ring no longer renders drawn, so the card is wrong when JS fails.'
        );

        // 2πr for r=62, at 91 out of 100 — roughly 35, and nowhere near the
        // full circumference that would mean an undrawn ring.
        $this->assertEqualsWithDelta(35.06, (float) $offset[1], 0.5);
    }

    /**
     * ⚠️ THE SCORE IS AN ANSWER, NOT AN ANIMATION.
     *
     * requestAnimationFrame stops in a backgrounded tab. Without a timer that
     * keeps running, somebody who taps and switches app comes back to a score
     * frozen part-counted — a number that was never real, sitting against a
     * named person's introduction.
     */
    public function test_the_score_is_correct_even_if_the_animation_never_finishes(): void
    {
        $js = $this->js();

        $this->assertMatchesRegularExpression(
            '/setTimeout\(\(\) => \{ value\.textContent = String\(score\); \}, CEREMONY\)/',
            $js,
            'Nothing guarantees the final score. If rAF is throttled the number stays wherever '
                .'it stopped.'
        );

        // ⚠️ And the easing must not be able to run backwards.
        $this->assertStringContainsString(
            'Math.max(0, Math.min((now - start) / CEREMONY, 1))',
            $js,
            'The count is no longer clamped at both ends. Unclamped below zero the easing '
                .'inverts and the card briefly shows a negative score.'
        );
    }

    /**
     * ⚠️ NOTHING HERE MAY EXPLAIN HOW COMPATIBILITY IS CALCULATED, and the
     * demo person must not be a real member.
     */
    public function test_the_demo_person_is_invented_and_the_scoring_is_not_explained(): void
    {
        $markup = $this->markup();

        // Not in DemoSeeder — a made-up score against a seeded member would be
        // attaching a fiction to somebody the product treats as real.
        $seeder = File::get(database_path('seeders/DemoSeeder.php'));

        $this->assertStringContainsString('Safiya', $markup);
        $this->assertStringNotContainsString(
            "'first' => 'Safiya'",
            $seeder,
            'The demo person now exists in the seeded data, so the page is showing an invented '
                .'compatibility score against a profile the product treats as real.'
        );

        foreach (['weight', 'algorithm', 'calculated', 'formula', 'points for'] as $tell) {
            $this->assertStringNotContainsStringIgnoringCase(
                $tell,
                $this->rendered(),
                "The demo explains how compatibility is scored (\"{$tell}\"), which is the one "
                    .'thing this section may never do.'
            );
        }
    }

    /**
     * ⚠️ TAP TARGET. The seal is the thing that looks tappable and is the
     * thing that must not be the target — 64px is over the 44px minimum on
     * paper but not once a thumb lands where the eye aimed rather than where
     * the seal is.
     */
    public function test_the_whole_card_is_the_target_and_it_is_a_real_button(): void
    {
        $markup = $this->markup();

        $this->assertMatchesRegularExpression(
            '/<button type="button" class="rv-demo__hit" data-demo-open>/',
            $markup,
            'The hit area is no longer a real button, so it cannot be reached by keyboard.'
        );

        $this->assertStringContainsString(
            'Open this introduction from Safiya',
            $markup,
            'The hit area has no accessible name.'
        );

        preg_match('/\.rv-demo__hit\s*\{([^}]*)\}/', $this->css(), $m);
        $this->assertNotEmpty($m, 'The hit area rule has gone.');
        $this->assertStringContainsString('inset: 0', $m[1], 'The target is no longer the whole card.');
    }

    /**
     * ⚠️ REDUCED MOTION DROPS THE CEREMONY, NEVER THE OUTCOME.
     *
     * The line underneath is the point of the section, so it must not be
     * attached to an animation that no longer runs.
     */
    public function test_the_copy_still_arrives_without_the_ceremony(): void
    {
        $this->assertStringContainsString(
            'REDUCED ? 200 : CEREMONY',
            $this->js(),
            'The closing line no longer arrives under reduced motion, so the section loses the '
                .'sentence it exists to deliver.'
        );

        $this->assertStringContainsString('Nobody browses anyone.', $this->markup());
    }

    /** The section renders on the live home page, with its card sealed. */
    public function test_the_section_renders_sealed(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('This is how an introduction arrives.', false);
        $response->assertSee('Tap to open it.', false);
        $response->assertSee('data-demo-card', false);

        // Sealed on arrival: the open state is added by the click, never
        // rendered by the server.
        $this->assertStringNotContainsString('rv-unsealing', $response->getContent());
    }
}
