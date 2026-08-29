<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * ⚠️ THE POSITIVE CONTROL FOR THE SUM-TO-100 RULE — R10 §8.2
 *
 * MatchingConfigTest asserts the five weights total 100. A guard nobody has
 * watched fail is a guard nobody knows is wired up, so this feeds it a set
 * summing to 99 and proves it rejects them.
 *
 * Joins PositiveControlsAreAliveTest.
 */
class WeightsSumControlTest extends TestCase
{
    public function test_the_real_weights_sum_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(config('matching.weights')));
    }

    /** A set summing to 99 must be rejected by the same check. */
    public function test_a_set_summing_to_ninety_nine_is_rejected(): void
    {
        config(['matching.weights' => [
            'preferences' => 40, 'values' => 25, 'lifestyle' => 20,
            'personality' => 10, 'physical' => 4,   // ← one short, deliberately
        ]]);

        $this->assertNotSame(
            100,
            array_sum(config('matching.weights')),
            'The sum-to-100 guard cannot tell a broken weight set from a valid one.'
        );
    }

    /** And a set missing a component must be rejected too. */
    public function test_a_set_missing_a_component_is_rejected(): void
    {
        config(['matching.weights' => [
            'preferences' => 40, 'values' => 25, 'lifestyle' => 20, 'personality' => 15,
        ]]);

        $this->assertNotSame(
            ['preferences', 'values', 'lifestyle', 'personality', 'physical'],
            array_keys(config('matching.weights')),
            'A four-component set passed the five-component check.'
        );
    }
}
