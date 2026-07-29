<?php

namespace Database\Factories;

use App\Models\EmailSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSend>
 */
class EmailSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'queued',
        ];
    }
}
