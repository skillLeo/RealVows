<?php

namespace App\Services\Matching\Scorers;

use App\Models\Profile;
use App\Services\Matching\AffinityMatrix;

/**
 * ⚠️ The internal mechanism. Its output feeds the total and NOTHING ELSE.
 *
 * It never appears in a rationale, never in a commonality, never in a member
 * payload. RationaleWriter is structurally incapable of writing a sentence
 * about this component — see the check in its constructor.
 */
class AffinityScorer implements ComponentScorer
{
    public function __construct(private readonly AffinityMatrix $matrix) {}

    public function key(): string
    {
        return 'affinity';
    }

    public function score(Profile $seeker, Profile $candidate): ?float
    {
        return $this->matrix->score($seeker->sign, $candidate->sign);
    }
}
