<?php

namespace Database\Factories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['facebook', 'instagram']),
            'from_id' => 1,
            'from_name' => fake()->name(),
            'message_id' => 1,
            'message' => fake()->text(),
            'message_created_time' => now(),
            'reply_id' => 1,
            'reply' => fake()->text(),
            'reply_created_time' => now(),
        ];
    }
}
