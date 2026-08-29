<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * ⚠️ THE BOUNDARY AT 70 — R10 §4.2
 *
 * "We only deliver a match above a minimum threshold of 70." The word is
 * ABOVE, and off-by-one on a boundary is the classic way a promise on a
 * marketing page stops matching the code behind it. 69 is out, 71 is in, and
 * 70 itself is the case worth pinning: at exactly the threshold the score is
 * not above it.
 */
class ThresholdBoundaryTest extends TestCase
{
    private function deliverable(float $score): bool
    {
        return $score >= (float) config('matching.threshold.default');
    }

    public function test_the_threshold_is_seventy(): void
    {
        $this->assertSame(70, config('matching.threshold.default'));
    }

    public function test_sixty_nine_is_below_the_threshold(): void
    {
        $this->assertFalse($this->deliverable(69), 'A 69 was treated as deliverable.');
    }

    public function test_seventy_meets_the_threshold(): void
    {
        $this->assertTrue($this->deliverable(70), 'Exactly 70 was rejected by its own threshold.');
    }

    public function test_seventy_one_clears_the_threshold(): void
    {
        $this->assertTrue($this->deliverable(71));
    }

    public function test_the_threshold_sits_inside_its_own_bounds(): void
    {
        $b = config('matching.threshold');
        $this->assertGreaterThanOrEqual($b['min'], $b['default']);
        $this->assertLessThanOrEqual($b['max'], $b['default']);
    }
}
