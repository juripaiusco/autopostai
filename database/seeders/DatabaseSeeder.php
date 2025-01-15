<?php

namespace Database\Seeders;

use App\Http\Controllers\Users;
use App\Models\Settings;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::factory()->create([
            'name' => 'Juri',
            'email' => 'juripaiusco.dev@gmail.com',
            'password' => '$2y$12$Lqd8n2GuU7rMXIkwoilTeeBQv0NQdzqMu51K1xptPi5Xi8kGSGwqe',
        ]);

        Settings::factory()->create([
            'user_id' => $admin->id
        ]);

        // -------------------------------------------------------

        $channels = (new Users())->get_channels();
        $channels['facebook']['on'] = '1';

        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Mario',
            'email' => 'mario@test.it',
            'password' => '$2y$12$Lqd8n2GuU7rMXIkwoilTeeBQv0NQdzqMu51K1xptPi5Xi8kGSGwqe',
            'channels' => json_encode($channels),
            'token_limit' => 10000
        ]);

        Settings::factory()->create([
            'user_id' => $user->id
        ]);
    }
}
