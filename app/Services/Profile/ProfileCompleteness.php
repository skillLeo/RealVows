<?php

namespace App\Services\Profile;

use App\Models\Profile;
use App\Support\ProfileOptions;

/**
 * What "complete" means, defined once.
 *
 * The same definition drives the percentage on the dashboard ring and the
 * check that decides whether a member may submit for verification — so the
 * ring can never read 100% while the submit button refuses them, which would
 * be maddening and would look broken.
 */
class ProfileCompleteness
{
    /**
     * Required fields per step. Photos are counted separately because they
     * live in their own table.
     */
    private const REQUIRED = [
        'personal' => ['first_name', 'last_name', 'date_of_birth', 'gender', 'seeking', 'city', 'country', 'willing_to_relocate'],
        'physical' => ['height_cm', 'body_type'],
        'lifestyle' => ['education', 'occupation', 'religion', 'faith_importance', 'smoking', 'drinking', 'has_children', 'wants_children'],
        'values' => ['relationship_goal', 'marriage_timeline', 'core_values', 'conflict_style', 'bio'],
    ];

    private const REQUIRED_PREFERENCES = ['age_min', 'age_max', 'distance_preference'];

    private const PHOTO_SLOTS = 4;

    /**
     * @return array{percent: int, missing: array<int, string>}
     */
    public function assess(Profile $profile): array
    {
        $profile->loadMissing(['preferences', 'photos']);

        $total = 0;
        $done = 0;
        $missing = [];

        foreach (self::REQUIRED as $step => $fields) {
            foreach ($fields as $field) {
                $total++;

                if ($this->filled($profile->{$field})) {
                    $done++;
                } else {
                    $missing[] = $this->label($step, $field);
                }
            }
        }

        foreach (self::REQUIRED_PREFERENCES as $field) {
            $total++;

            if ($this->filled($profile->preferences?->{$field})) {
                $done++;
            } else {
                $missing[] = $this->label('preferences', $field);
            }
        }

        // Four photos, each counted, so a member who has uploaded two sees
        // the ring move rather than sitting still until all four land.
        $uploaded = $profile->photos->count();
        $total += self::PHOTO_SLOTS;
        $done += min($uploaded, self::PHOTO_SLOTS);

        if ($uploaded < self::PHOTO_SLOTS) {
            $shortfall = self::PHOTO_SLOTS - $uploaded;
            $missing[] = $shortfall === 1
                ? 'One more photo'
                : "{$shortfall} more photos";
        }

        // The bio has a floor as well as a presence check — three words is
        // not a bio, and matching on it would be meaningless.
        if ($this->filled($profile->bio) && $this->wordCount($profile->bio) < ProfileOptions::BIO_MIN_WORDS) {
            $missing[] = 'A slightly longer bio — at least '.ProfileOptions::BIO_MIN_WORDS.' words';
        }

        return [
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'missing' => $missing,
        ];
    }

    public function isSubmittable(Profile $profile): bool
    {
        return $this->assess($profile)['missing'] === [];
    }

    public function wordCount(?string $text): int
    {
        return $text ? str_word_count(strip_tags($text)) : 0;
    }

    private function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        // Careful: `false` is a legitimate answer to "willing to relocate?"
        // and 0 is a legitimate personality-scale value. Only null and the
        // empty string count as unanswered.
        return $value !== null && $value !== '';
    }

    private function label(string $step, string $field): string
    {
        $names = [
            'first_name' => 'Your preferred first name',
            'last_name' => 'Your last name',
            'date_of_birth' => 'Your date of birth',
            'gender' => 'Your gender',
            'seeking' => 'Who you are seeking',
            'city' => 'Your city',
            'country' => 'Your country',
            'willing_to_relocate' => 'Whether you would relocate',
            'height_cm' => 'Your height',
            'body_type' => 'Your body type',
            'education' => 'Your education',
            'occupation' => 'Your occupation',
            'religion' => 'Your religion',
            'faith_importance' => 'How important shared faith is',
            'smoking' => 'Whether you smoke',
            'drinking' => 'Whether you drink',
            'has_children' => 'Whether you have children',
            'wants_children' => 'Whether you want children',
            'relationship_goal' => 'What you are looking for',
            'marriage_timeline' => 'Your timeframe for a lasting relationship',
            'core_values' => 'Your five core values',
            'conflict_style' => 'How you handle disagreement',
            'bio' => 'A few words about you',
            'age_min' => 'The youngest age you would consider',
            'age_max' => 'The oldest age you would consider',
            'distance_preference' => 'How far you would look',
        ];

        return $names[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
