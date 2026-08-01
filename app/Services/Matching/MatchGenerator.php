<?php

namespace App\Services\Matching;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Services\Matching\Copy\CommonalityFinder;
use App\Services\Matching\Copy\RationaleWriter;
use App\Support\MatchingDials;
use Illuminate\Support\Facades\DB;

/**
 * Turns one verified profile into a set of introductions.
 *
 * Filter → score → threshold → order → take → write. Nothing here talks to
 * the network, and the whole run is idempotent: called twice it produces the
 * same rows, because the pool excludes pairs a row already exists for.
 */
class MatchGenerator
{
    public function __construct(
        private readonly CandidatePool $pool,
        private readonly CompatibilityScorer $scorer,
        private readonly RationaleWriter $rationale,
        private readonly CommonalityFinder $commonalities,
        private readonly MatchingDials $dials,
    ) {}

    /**
     * @return int How many introductions were written.
     */
    public function generateFor(Profile $seeker): int
    {
        // Belt and braces. The job checks too, but this is the last gate
        // before rows get written, and it is cheap.
        if (! $seeker->isVerified()) {
            return 0;
        }

        $threshold = $this->dials->threshold();
        $wanted = $this->dials->teaserCount();

        $existing = MatchRecord::query()
            ->where('seeker_profile_id', $seeker->id)
            ->where('status', '!=', MatchRecord::STATUS_EXPIRED)
            ->count();

        $room = $wanted - $existing;

        if ($room <= 0) {
            $seeker->forceFill(['last_matched_at' => now()])->save();

            return 0;
        }

        $scored = [];

        foreach ($this->pool->for($seeker) as $candidate) {
            $result = $this->scorer->score($seeker, $candidate);

            if ($result['total'] < $threshold) {
                continue;
            }

            $scored[] = ['candidate' => $candidate, 'result' => $result];
        }

        /*
         * ── Ordering ─────────────────────────────────────────────────────
         *
         * Score first, obviously. Then the fairness tiebreak: whoever has
         * been waiting longest since verification surfaces first, so a
         * platform with more women than men does not repeatedly show the
         * same handful of people and leave the rest invisible.
         *
         * Then id, so the order is total and the run is reproducible.
         */
        usort($scored, function (array $a, array $b) {
            if ($a['result']['total'] !== $b['result']['total']) {
                return $b['result']['total'] <=> $a['result']['total'];
            }

            $aVerified = $a['candidate']->verification?->reviewed_at;
            $bVerified = $b['candidate']->verification?->reviewed_at;

            if ($aVerified && $bVerified && ! $aVerified->eq($bVerified)) {
                return $aVerified <=> $bVerified;
            }

            return $a['candidate']->id <=> $b['candidate']->id;
        });

        $written = 0;

        foreach (array_slice($scored, 0, $room) as $entry) {
            $written += $this->write($seeker, $entry['candidate'], $entry['result']);
        }

        $seeker->forceFill(['last_matched_at' => now()])->save();

        return $written;
    }

    private function write(Profile $seeker, Profile $candidate, array $result): int
    {
        /*
         * The unique pair constraint is what makes concurrent runs safe, not
         * the check in CandidatePool. Two nightly sweeps overlapping would
         * both see an empty pool for the same pair; only one insert survives,
         * and losing that race is a no-op rather than a duplicate.
         */
        try {
            return DB::transaction(function () use ($seeker, $candidate, $result) {
                MatchRecord::create([
                    'seeker_profile_id' => $seeker->id,
                    'matched_profile_id' => $candidate->id,
                    'total_score' => $result['total'],
                    'score_breakdown' => $result,
                    'compatibility_rationale' => $this->rationale->write($seeker, $candidate, $result),
                    'commonalities' => $this->commonalities->find($seeker, $candidate),
                    'status' => MatchRecord::STATUS_TEASER,
                    'generated_at' => now(),
                ]);

                return 1;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return 0;
        }
    }
}
