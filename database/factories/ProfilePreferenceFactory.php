<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfilePreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfilePreference>
 */
class ProfilePreferenceFactory extends Factory
{
    protected $model = ProfilePreference::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'age_min' => 25,
            'age_max' => 40,
            'height_min_cm' => 150,
            'height_max_cm' => 195,
            'body_types' => ['slim', 'athletic', 'average'],
            'distance_preference' => 'same_country',
            'deal_breakers' => [],
            'preferred_qualities' => ['patient', 'kind'],
        ];
    }
}
