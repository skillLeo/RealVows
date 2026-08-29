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
}
