<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
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
        $channel_array = array('facebook', 'instagram');
        return [
            'channel' => $channel_array[array_rand($channel_array)],
            'from_id' => 1,
            'from_name' => fake()->name(),
            'message_id' => 1,
            'message' => fake()->text(),
            'message_created_time' => date('Y-m-d H:i:s', time()),
            'reply_id' => 1,
            'reply' => fake()->text(),
            'reply_created_time' => date('Y-m-d H:i:s', time()),
        ];
    }
}
