<?php

namespace App\Actions\Profile;

use App\Jobs\RegenerateMatchesForProfile;
use App\Models\Profile;
use App\Services\Profile\ProfileCompleteness;
use App\Services\Matching\MatchingFieldsHash;
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
             * ⚠️ THE MEMBER'S OWN WORDS, KEPT WHEN THEY ACCEPT A REWRITE.
             *
             * `bio_original` existed as a column for a session with nothing
             * writing to it, which is worse than not having it: the audit trail
             * looked present and was empty. If a member is ever accused of
             * misrepresenting themselves, the only fair question is what THEY
             * said, and an accepted rewrite is otherwise indistinguishable from
             * their own typing.
             *
             * ⚠️ IT IS WRITTEN ONCE AND NEVER OVERWRITTEN. A member who accepts
             * a rewrite, edits it, and saves again must not have their original
             * replaced by the rewrite they have since edited — that would
             * quietly record the machine's words as theirs.
             */
            if (($profileData['bio_original'] ?? null) && ! $profile->getOriginal('bio_original')) {
                $profile->bio_rewritten_at = now();
                $profile->bio_rewritten_by = 'profile_writer';
            } elseif (array_key_exists('bio_original', $profileData)) {
                // Do not let a later save clear or replace what they typed.
                $profile->bio_original = $profile->getOriginal('bio_original') ?: $profileData['bio_original'];
            }
            /*
             * ⚠️ DATE OF BIRTH IS NOW ONLY AN AGE.
             *
             * Saving it used to recalculate two hidden columns behind an
             * date-of-birth-derived affinity component worth 25 points of the match
             * score. That component, its matrix and its calculator are deleted.
             * Date of birth survives for the 18+ gate and the age shown on a
             * profile, and for nothing else.
             */

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
