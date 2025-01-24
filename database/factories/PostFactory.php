<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->text(20),
            'ai_prompt_post' => fake()->paragraph(),
            'ai_content' => fake()->paragraph(),
            'ai_prompt_comment' => fake()->paragraph(),
            'published_at' => date('Y-m-d H:i:00', time()),
            'published' => rand(0, 1),
        ];
    }
}
