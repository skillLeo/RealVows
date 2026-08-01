<?php

namespace App\Services\Matching;

use App\Models\Profile;

/**
 * A fingerprint of only the fields the engine actually reads.
 *
 * Somebody correcting a typo in their occupation, or rewriting their bio,
 * should not queue a full re-run against every verified member on the
 * platform. Comparing this hash answers "did anything that affects a score
 * actually change?" in one string comparison.
 *
 * ⚠️ If you add a field to a scorer, add it here too. A scorer reading a
 * field this hash ignores means an edit to that field silently never
 * regenerates anything, and the member's matches quietly go stale.
 */
class MatchingFieldsHash
{
    /** Every column any scorer or hard filter reads. */
    private const PROFILE_FIELDS = [
        'date_of_birth', 'gender', 'seeking', 'city', 'country', 'willing_to_relocate',
        'sign', 'element',
        'height_cm', 'body_type', 'body_type_is_dealbreaker',
        'education', 'religion', 'faith_importance', 'smoking', 'drinking',
        'has_children', 'wants_children', 'pets', 'languages',
        'marriage_timeline', 'core_values', 'conflict_style', 'love_languages',
        'interests', 'personality_scales',
    ];

    private const PREFERENCE_FIELDS = [
        'age_min', 'age_max', 'height_min_cm', 'height_max_cm',
        'body_types', 'distance_preference', 'deal_breakers',
    ];

    public function for(Profile $profile): string
    {
        $payload = [];

        foreach (self::PROFILE_FIELDS as $field) {
            $payload["p.{$field}"] = $this->stable($profile->getAttribute($field));
        }

        $preferences = $profile->preferences;

        foreach (self::PREFERENCE_FIELDS as $field) {
            $payload["r.{$field}"] = $this->stable($preferences?->getAttribute($field));
        }

        ksort($payload);

        return hash('sha256', json_encode($payload));
    }

    /**
     * Arrays are sorted before hashing ONLY where their order is meaningless.
     * `core_values` is ranked and its order carries meaning, so reordering it
     * genuinely is a change worth regenerating for.
     */
    private function stable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            return array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? null : (string) $value;
    }
}
