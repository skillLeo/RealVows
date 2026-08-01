<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Verification>
 */
class VerificationFactory extends Factory
{
    protected $model = Verification::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'status' => Verification::STATUS_PENDING,
            'code' => strtoupper(Str::random(6)),
        ];
    }

    public function verified(): static
    {
        return $this->state([
            'status' => Verification::STATUS_VERIFIED,
            'reviewed_at' => now(),
        ]);
    }
}
