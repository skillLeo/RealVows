<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;

/**
 * One component of the compatibility score.
 *
 * Every implementation must be PURE: same two profiles in, same number out,
 * every time, with no query, no clock, no randomness and no network call.
 * The engine runs thousands of these per batch on shared hosting, and a
 * score that cannot be reproduced cannot be explained to a member who asks
 * why they were shown someone.
 */
interface ComponentScorer
{
    /** Matches a key in config('matching.weights'). */
    public function key(): string;

    /**
     * @return float|null 0–100, or NULL when neither profile carries the data
     *                    this component reads.
     *
     * ⚠️ Null is not zero. Returning zero for missing data would punish an
     * incomplete profile twice — once by having less to match on, and again
     * by being scored as actively incompatible. Null tells the orchestrator
     * to redistribute this component's weight across the components that do
     * have something to say.
     */
    public function score(Profile $seeker, Profile $candidate): ?float;
}
