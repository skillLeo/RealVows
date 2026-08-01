<?php

namespace App\Actions\Profile;

use App\Jobs\RegenerateMatchesForProfile;
use App\Models\Profile;
use App\Services\Profile\ProfileCompleteness;
use App\Services\Matching\MatchingFieldsHash;
use App\Services\Sign\SignCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveProfileStep
{
    /** Fields on `profile_preferences` rather than `profiles`. */
    private const PREFERENCE_FIELDS = [
        'age_min', 'age_max', 'height_min_cm', 'height_max_cm',
        'body_types', 'distance_preference', 'deal_breakers', 'preferred_qualities',
    ];

    public function __construct(
        private readonly SignCalculator $signs,
        private readonly ProfileCompleteness $completeness,
        private readonly MatchingFieldsHash $hashes,
    ) {}

    /**
     * Persist one step and return the fresh completeness figure.
     *
     * @param  array<string, mixed>  $data  already validated
     * @return array{percent: int, missing: array<int, string>}
     */
    public function handle(Profile $profile, string $step, array $data, int $stepNumber): array
    {
        return DB::transaction(function () use ($profile, $step, $data, $stepNumber) {
            $preferenceData = array_intersect_key($data, array_flip(self::PREFERENCE_FIELDS));
            $profileData = array_diff_key($data, $preferenceData);

            if ($profileData !== []) {
                $profile->fill($profileData);
            }

            /*
             * ⚠️ The silent derivation.
             *
             * Saving a date of birth recalculates the two internal columns
             * behind the affinity component — see config/matching.php for
             * what that component is currently worth. Nothing about it is
             * returned to the browser, nothing is shown to the member, and
             * there is no reveal card — the approved design had one and it
             * was deliberately removed. See SignCalculator for the reasoning.
             */
            if ($profile->isDirty('date_of_birth') && $profile->date_of_birth) {
                $derived = $this->signs->for(Carbon::parse($profile->date_of_birth));
                $profile->sign = $derived['sign'];
                $profile->element = $derived['element'];
            }

            // Furthest step reached, so returning members land where they
            // stopped rather than at the beginning.
            $profile->current_step = max($profile->current_step, $stepNumber);

            $assessment = $this->completeness->assess($profile);
            $profile->completeness_percent = $assessment['percent'];

            $profile->save();

            if ($preferenceData !== []) {
                $profile->preferences()->updateOrCreate(
                    ['profile_id' => $profile->id],
                    $preferenceData
                );

                // Preferences count towards completeness, so it has to be
                // recomputed once they have actually been written.
                $assessment = $this->completeness->assess($profile->fresh(['preferences', 'photos']));
                $profile->completeness_percent = $assessment['percent'];
                $profile->saveQuietly();
            }

            $this->queueRegenerationIfScoringChanged($profile);

            return $assessment;
        });
    }

    /**
     * An edit only regenerates matches when it touched something the engine
     * actually reads.
     *
     * This endpoint is hit every 800ms while somebody is typing, so the
     * cheap check comes first: hash the fields that affect scoring and
     * compare. Rewriting a bio or fixing a typo in an occupation changes
     * nothing here and queues nothing.
     *
     * The job is delayed and unique per profile, so somebody working through
     * six fields queues one run rather than six — the uniqueness lock does
     * the debouncing.
     */
    private function queueRegenerationIfScoringChanged(Profile $profile): void
    {
        if (! $profile->isVerified()) {
            return;
        }

        $hash = $this->hashes->for($profile->fresh(['preferences']));

        if ($hash === $profile->matching_fields_hash) {
            return;
        }

        $profile->forceFill(['matching_fields_hash' => $hash])->saveQuietly();

        RegenerateMatchesForProfile::dispatch($profile->id)
            ->delay(now()->addMinutes(config('matching.regenerate_debounce_minutes')))
            ->afterCommit();
    }
}
