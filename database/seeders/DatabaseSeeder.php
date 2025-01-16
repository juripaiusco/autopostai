<?php

namespace Database\Seeders;

use App\Http\Controllers\Users;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Settings;
use App\Models\Token_log;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Insert admin user --------------------------------------
        $admin = User::factory()->create([
            'name' => 'Juri',
            'email' => 'juripaiusco.dev@gmail.com',
            'password' => Hash::make('12345'),
        ]);

        Settings::factory()->create([
            'user_id' => $admin->id
        ]);

        // Insert Mario user --------------------------------------

        $channels = (new Users())->get_channels();
        $channels['facebook']['on'] = '1';
        $channels['instagram']['on'] = '1';

        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Mario',
            'email' => 'mario@test.it',
            'password' => Hash::make('12345'),
            'channels' => json_encode($channels),
            'tokens_limit' => 10000
        ]);

        Settings::factory()->create([
            'user_id' => $user->id
        ]);

        $posts = Post::factory(2)->create([
            'user_id' => $user->id,
            'channels' => json_encode($channels)
        ]);

        foreach ($posts as $post) {
            Token_log::factory()->create([
                'user_id' => $user->id,
                'type' => 'post',
                'reference_id' => $post->id,
                'tokens_used' => rand(50, 1000),
            ]);

            $comments = Comment::factory(3)->create([
                'post_id' => $post->id
            ]);

            foreach ($comments as $comment) {
                Token_log::factory()->create([
                    'user_id' => $user->id,
                    'type' => 'comment',
                    'reference_id' => $comment->id,
                    'tokens_used' => rand(50, 200),
                ]);
            }
        }

        // Insert Luigi user --------------------------------------

        $channels = (new Users())->get_channels();
        $channels['facebook']['on'] = '1';

        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Luigi',
            'email' => 'luigi@test.it',
            'password' => Hash::make('12345'),
            'channels' => json_encode($channels),
            'tokens_limit' => 10000
        ]);

        Settings::factory()->create([
            'user_id' => $user->id
        ]);

        // Insert Manager --------------------------------------

        $channels = (new Users())->get_channels();

        $manager = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Pietro',
            'email' => 'pietro@prova.it',
            'password' => Hash::make('12345'),
            'channels' => json_encode($channels),
            'child_on' => 1,
            'child_max' => 2,
        ]);

        Settings::factory()->create([
            'user_id' => $manager->id
        ]);

            // Insert Sub User --------------------------------------

        $channels = (new Users())->get_channels();
        $channels['facebook']['on'] = '1';
        $channels['instagram']['on'] = '1';

        $array_subuser = [
            [
                'name' => 'Bepi',
                'email' => 'bepi@prova.it',
            ], [
                'name' => 'Toni',
                'email' => 'toni@prova.it',
            ]
        ];
        foreach ($array_subuser as $item) {

            $user = User::factory()->create([
                'parent_id' => $manager->id,
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => Hash::make('12345'),
                'channels' => json_encode($channels),
                'tokens_limit' => 10000
            ]);

            Settings::factory()->create([
                'user_id' => $user->id
            ]);

            $posts = Post::factory(2)->create([
                'user_id' => $user->id,
                'channels' => json_encode($channels)
            ]);

            foreach ($posts as $post) {
                Token_log::factory()->create([
                    'user_id' => $user->id,
                    'type' => 'post',
                    'reference_id' => $post->id,
                    'tokens_used' => rand(50, 1000),
                ]);

                $comments = Comment::factory(3)->create([
                    'post_id' => $post->id
                ]);

                foreach ($comments as $comment) {
                    Token_log::factory()->create([
                        'user_id' => $user->id,
                        'type' => 'comment',
                        'reference_id' => $comment->id,
                        'tokens_used' => rand(50, 200),
                    ]);
                }
            }
        }
    }
}
