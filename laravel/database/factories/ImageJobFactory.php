<?php

namespace Database\Factories;

use App\Models\ImageJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImageJob>
 */
class ImageJobFactory extends Factory
{
    protected $model = ImageJob::class;

    public function definition(): array
    {
        return [
            'status'     => 'completed',
            'image_url'  => 'https://picsum.photos/seed/' . $this->faker->word() . '/800/600',
            'prompt'     => $this->faker->sentence(),
            'model'      => 'dall-e-3',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
