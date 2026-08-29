<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ⚠️ THE PAGE AND THE ENGINE, PROVEN EQUAL — R10 §8.1
 *
 * The Science of Harmony section publishes five percentages and a threshold.
 * Those are claims about how this product works, made to people deciding
 * whether to trust it with their marriage. If a weight is retuned in config and
 * the page keeps its old number, the page becomes a lie about our own product
 * and nothing anywhere notices.
 *
 * That is not hypothetical. The weights moved 28/25/17/17/13 → 40/25/20/10/5
 * and the threshold 65 → 70 in this very round. Without this test the page
 * would still be advertising the old five.
 *
 * The rule this enforces: PERCENTAGES ON A PUBLIC PAGE ARE RENDERED FROM
 * config('matching.weights'), NEVER TYPED INTO A TEMPLATE.
 */
class PageMatchesEngineTest extends TestCase
{
    /** Every weight in the engine appears on the page, as a percentage. */
    public function test_the_science_section_publishes_the_engine_weights(): void
    {
        $html = $this->get('/how-it-works')->assertOk()->getContent()
            .$this->get('/')->assertOk()->getContent();

        foreach (config('matching.weights') as $component => $weight) {
            $this->assertStringContainsString(
                "{$weight}%",
                $html,
                "The engine weights [{$component}] at {$weight}%, and no public page says so. "
                    .'Either the page hardcodes a stale number or the row is missing. '
                    .'Render these from config(\'matching.weights\'); never type them into a template.'
            );
        }
    }

    /**
     * ⚠️ THE CONTROL. If the page were hardcoded, this would still pass — so
     * this test deliberately disagrees with the page and MUST fail to find its
     * number, proving the assertion above has teeth.
     */
    public function test_a_weight_the_engine_does_not_carry_is_absent_from_the_page(): void
    {
        $html = $this->get('/')->getContent();
        $real = array_values(config('matching.weights'));

        // 37 is not one of 40/25/20/10/5 and must appear nowhere as a weight.
        $this->assertNotContains(37, $real, 'Fixture clash: pick a percentage the engine does not use.');
        $this->assertStringNotContainsString(
            '37%',
            $html,
            'The page shows 37% as a compatibility weight and the engine carries no such component. '
                .'A percentage on this page that the engine cannot account for is a claim we cannot honour.'
        );
    }

    /** The published threshold is the configured threshold. */
    public function test_the_published_threshold_equals_the_configured_threshold(): void
    {
        $threshold = config('matching.threshold.default');

        $this->assertStringContainsString(
            "threshold of {$threshold}",
            $this->get('/')->assertOk()->getContent(),
            "Configuration delivers above {$threshold} and the home page states a different number. "
                .'The page must render this from config, not restate it.'
        );
    }

    /**
     * ⚠️ FIVE COMPONENTS, NOT SIX — R10 §4.3.
     *
     * The reference page this design copies described six dimensions, because
     * its sixth was astrology. Ours has five and the sixth is not coming back.
     */
    public function test_no_public_surface_claims_six_components(): void
    {
        $this->assertCount(5, config('matching.weights'));

        foreach (['/', '/how-it-works', '/faq', '/about'] as $route) {
            $html = strtolower($this->get($route)->getContent());

            foreach (['six dimensions', 'six components', 'six factors', '6 dimensions', '6 components'] as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $html,
                    "[{$route}] still advertises {$phrase}. The engine has five and the sixth was astrology."
                );
            }
        }
    }

    /**
     * ⚠️ THE METER IS A DIAGRAM OF THE ENGINE, SO IT IS HELD TO THE ENGINE —
     * R10-D §2.
     *
     * The arc is five segments, each carrying one component's share, written
     * into the markup as a --share custom property. Nothing stops somebody
     * typing a number there, so this reads them back out of the rendered page
     * and compares them to config('matching.weights').
     *
     * The shares carry a 0.8 gap so the joins are legible, which is why this
     * compares against weight − 0.8 rather than the weight itself.
     */
    public function test_the_meter_segments_carry_the_engine_weights(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/rv-gauge__seg[^>]*--share:\s*([0-9.]+)/', $html, $found);

        /* ⚠️ THE EXTRACTION IS PROVED BEFORE IT IS COMPARED. A regex that stops
           matching after a markup change would otherwise report a flawless
           meter in exactly the same words as a real one. */
        $this->assertNotEmpty($found[1], 'No meter segments were extracted. The reader is looking at the wrong markup.');

        $weights = array_values(config('matching.weights'));
        rsort($weights);

        $this->assertCount(
            count($weights),
            $found[1],
            'The meter does not draw one segment per component. It has '.count($found[1])
                .' and the engine carries '.count($weights).'.'
        );

        $drawn = array_map('floatval', $found[1]);
        $expected = array_map(fn ($w) => max($w - 0.8, 0.6), $weights);

        $this->assertSame(
            $expected,
            $drawn,
            'A meter segment draws a share the engine does not carry. The arc must be rendered '
                .'from config(\'matching.weights\'), never typed.'
        );
    }

    /** The segments must lay end to end and reach the engine's own total. */
    public function test_the_meter_offsets_accumulate_to_the_full_score(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/rv-gauge__seg[^>]*--offset:\s*([0-9.]+)/', $html, $found);

        $this->assertNotEmpty($found[1], 'No meter offsets were extracted.');

        $weights = array_values(config('matching.weights'));
        rsort($weights);

        $carried = 0;
        $expected = [];

        foreach ($weights as $w) {
            $expected[] = (float) $carried;
            $carried += $w;
        }

        $this->assertSame($expected, array_map('floatval', $found[1]), 'The meter segments overlap or leave gaps.');
        $this->assertSame(100, $carried, 'The meter does not build to 100.');
    }

    /** ⚠️ The floor stays marked on the arc, at the configured threshold. */
    public function test_the_meter_marks_the_threshold_as_the_floor(): void
    {
        $html = $this->get('/')->getContent();
        $threshold = config('matching.threshold.default');

        $this->assertMatchesRegularExpression(
            '/rv-gauge__floor[^>]*--at:\s*'.$threshold.'\b/',
            $html,
            "The floor is not drawn at {$threshold}. A meter that fills to 100 with no floor on it "
                .'reads as a promise that somebody will score 100.'
        );

        $this->assertStringContainsString(
            "{$threshold} is the floor an introduction must clear",
            $html,
            'The floor is drawn but not labelled, so it is a mark nobody can interpret.'
        );
    }

    /**
     * ⚠️ THE COMPLETED STATE IS WHAT THE SERVER RENDERS — PROVEN, NOT ASSUMED.
     *
     * R10-D §2 requires reduced motion to show all five components and the
     * total. The script only ever ADDS motion, so the way to prove that state
     * is to check what arrives before any script runs: every segment at full
     * length, every chip present, and the real total in the figure.
     */
    public function test_the_served_markup_is_already_the_completed_state(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(
            count(config('matching.weights')),
            substr_count($html, 'rv-gauge__chip"'),
            'The chips are not all rendered server-side, so reduced motion would show fewer than five.'
        );

        $this->assertMatchesRegularExpression(
            '/data-meter-total[^>]*>\s*'.array_sum(config('matching.weights')).'\s*</',
            $html,
            'The figure does not show the completed total in the served markup. With no JavaScript, '
                .'or under reduced motion, the meter would read zero.'
        );

        // No inline style may pre-hide a segment; hiding belongs to the
        // no-preference media query alone.
        $this->assertStringNotContainsString(
            'rv-gauge__seg" style="opacity:0',
            $html,
            'A segment is hidden in the served markup, so the completed state is not the default.'
        );
    }
}
