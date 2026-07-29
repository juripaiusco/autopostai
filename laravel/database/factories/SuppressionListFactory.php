<?php

namespace Database\Factories;

use App\Models\SuppressionList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuppressionList>
 */
class SuppressionListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => fake()->randomElement(['hard_bounce', 'complaint', 'unsubscribe']),
        ];
    }
}
