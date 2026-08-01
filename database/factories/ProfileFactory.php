<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Sign\SignCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-45 years', '-25 years');

        /*
         * Derived here rather than hardcoded, exactly as the real save path
         * does it. A factory that set these directly could drift from the
         * calculator and hide a bug in the thing being tested.
         */
        $derived = (new SignCalculator)->for(\Illuminate\Support\Carbon::instance($dob));

        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => $dob,
            'gender' => 'woman',
            'seeking' => 'man',
            'city' => 'Kingston',
            'country' => 'Jamaica',
            'willing_to_relocate' => false,
            'sign' => $derived['sign'],
            'element' => $derived['element'],
            'height_cm' => fake()->numberBetween(150, 190),
            'body_type' => 'average',
            'education' => 'Bachelor degree',
            'occupation' => fake()->jobTitle(),
            'religion' => 'Islam',
            'faith_importance' => 'important',
            'smoking' => 'never',
            'drinking' => 'never',
            'has_children' => 'none',
            'wants_children' => 'yes',
            'pets' => ['none'],
            'languages' => ['English'],
            'marriage_timeline' => 'one_to_two',
            'core_values' => ['family', 'faith', 'honesty', 'kindness', 'loyalty'],
            'conflict_style' => 'discuss_now',
            'love_languages' => ['time', 'words'],
            'bio' => fake()->paragraph(),
            'interests' => ['reading', 'cooking', 'travel'],
            'personality_scales' => ['social' => 50, 'planning' => 40, 'decisions' => 55, 'outlook' => 45],
            'completeness_percent' => 100,
            'current_step' => 7,
            'submitted_at' => now(),
        ];
    }

    /** A profile that can actually enter the matching pool. */
    public function verified(): static
    {
        return $this->afterCreating(function (Profile $profile) {
            Verification::factory()->create([
                'profile_id' => $profile->id,
                'status' => Verification::STATUS_VERIFIED,
                'reviewed_at' => now(),
            ]);
        });
    }

    public function man(): static
    {
        return $this->state(['gender' => 'man', 'seeking' => 'woman']);
    }

    public function woman(): static
    {
        return $this->state(['gender' => 'woman', 'seeking' => 'man']);
    }

    public function bornOn(string $date): static
    {
        $derived = (new SignCalculator)->for(\Illuminate\Support\Carbon::parse($date));

        return $this->state([
            'date_of_birth' => $date,
            'sign' => $derived['sign'],
            'element' => $derived['element'],
        ]);
    }
}
