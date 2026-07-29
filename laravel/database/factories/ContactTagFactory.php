<?php

namespace Database\Factories;

use App\Models\ContactTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactTag>
 */
class ContactTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
