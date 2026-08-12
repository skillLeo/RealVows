<?php

namespace Tests\Unit;

use App\Support\MatchingDials;
use Tests\TestCase;

class MatchingConfigTest extends TestCase
{
    /**
     * ⚠️ IF THIS FAILS, SOMEBODY CHANGED ONE WEIGHT WITHOUT CHANGING ANOTHER.
     *
     * The components are percentages of one score. If they stop summing to
     * 100 the total silently stops meaning "out of 100" — every score on the
     * platform shifts, no error is raised, and the first sign of trouble is
     * a member asking why their match dropped from 82 to 74 overnight.
     */
    public function test_the_weights_sum_to_one_hundred(): void
    {
        $weights = config('matching.weights');

        $this->assertSame(
            100,
            array_sum($weights),
            'Component weights must total 100. They currently total '.array_sum($weights)
                .': '.json_encode($weights).'. Adjust another component to compensate.'
        );
    }

    public function test_every_expected_component_is_present(): void
    {
        $this->assertSame(
            ['preferences', 'values', 'lifestyle', 'personality', 'physical'],
            array_keys(config('matching.weights')),
            'A scorer exists for each of these keys. Adding or removing one here without '
                .'adding or removing the matching scorer will produce scores that silently omit a component.'
        );
    }

    public function test_no_weight_is_zero_or_negative(): void
    {
        foreach (config('matching.weights') as $component => $weight) {
            $this->assertGreaterThan(
                0,
                $weight,
                "Component [{$component}] carries no weight. Remove the scorer instead of zeroing it, "
                    .'so the dead code does not stay behind pretending to matter.'
            );
        }
    }

    public function test_the_dial_bounds_are_coherent(): void
    {
        foreach (['threshold', 'teaser_count'] as $dial) {
            $bounds = config("matching.{$dial}");

            $this->assertLessThan($bounds['max'], $bounds['min'], "{$dial} bounds are inverted.");
            $this->assertGreaterThanOrEqual($bounds['min'], $bounds['default'], "{$dial} default is below its own minimum.");
            $this->assertLessThanOrEqual($bounds['max'], $bounds['default'], "{$dial} default is above its own maximum.");
        }
    }

    public function test_weights_are_exposed_for_display_heaviest_first(): void
    {
        $weights = MatchingDials::weights();

        $this->assertSame(array_values($weights), collect($weights)->sortDesc()->values()->all());
        $this->assertSame(100, array_sum($weights));
    }
}
