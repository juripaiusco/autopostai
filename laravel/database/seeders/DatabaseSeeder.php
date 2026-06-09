<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\ImageJob;
use App\Models\Post;
use App\Models\Settings;
use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin --------------------------------------

        $admin = User::factory()->create([
            'name' => 'Juri',
            'email' => 'juripaiusco.dev@gmail.com',
            'password' => Hash::make('12345'),
        ]);

        Settings::factory()->create(['user_id' => $admin->id]);

        // Mario (canali facebook + instagram attivi) -------------

        $channels = $this->defaultChannels();
        $channels['facebook']['on'] = '1';
        $channels['instagram']['on'] = '1';

        $mario = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Mario',
            'email' => 'mario@test.it',
            'password' => Hash::make('12345'),
            'channels' => $channels,
            'tokens_limit' => 10000,
            'image_model_limit' => 20,
        ]);

        Settings::factory()->create(['user_id' => $mario->id]);

        $this->seedPostsWithComments($mario, $mario, 2, $channels);

        // Luigi (solo canale facebook attivo) ---------------------

        $channels = $this->defaultChannels();
        $channels['facebook']['on'] = '1';

        $luigi = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Luigi',
            'email' => 'luigi@test.it',
            'password' => Hash::make('12345'),
            'channels' => $channels,
            'tokens_limit' => 10000,
        ]);

        Settings::factory()->create(['user_id' => $luigi->id]);

        // Pietro (manager: può gestire fino a 2 sub-utenti) -------

        $manager = User::factory()->create([
            'parent_id' => $admin->id,
            'name' => 'Pietro',
            'email' => 'pietro@prova.it',
            'password' => Hash::make('12345'),
            'channels' => $this->defaultChannels(),
            'child_on' => 1,
            'child_max' => 2,
        ]);

        Settings::factory()->create(['user_id' => $manager->id]);

        // Sub-utenti di Pietro: Bepi e Toni ------------------------

        $channels = $this->defaultChannels();
        $channels['facebook']['on'] = '1';
        $channels['instagram']['on'] = '1';

        foreach ([
            ['name' => 'Bepi', 'email' => 'bepi@prova.it'],
            ['name' => 'Toni', 'email' => 'toni@prova.it'],
        ] as $item) {
            $subUser = User::factory()->create([
                'parent_id' => $manager->id,
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => Hash::make('12345'),
                'channels' => $channels,
                'tokens_limit' => 10000,
                'image_model_limit' => 20,
            ]);

            Settings::factory()->create(['user_id' => $subUser->id]);

            $this->seedPostsWithComments($subUser, $subUser, 5, $channels, $subUser->name . ' - ');
        }
    }

    /**
     * Crea $count post per $owner (con titolo opzionalmente prefissato) e,
     * per ogni post pubblicato, i relativi token_log e commenti con risposta AI.
     */
    private function seedPostsWithComments(User $owner, User $createdBy, int $count, array $channels, string $titlePrefix = ''): void
    {
        $posts = Post::factory($count)->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $createdBy->id,
            'title' => $titlePrefix !== '' ? $titlePrefix . fake()->text(60) : fake()->text(60),
            'channels' => $channels,
        ]);

        foreach ($posts as $post) {
            if ($post->published != '1') {
                continue;
            }

            TokenLog::factory()->create([
                'user_id' => $owner->id,
                'type' => 'post',
                'reference_id' => $post->id,
                'tokens_used' => rand(50, 1000),
            ]);

            $comments = Comment::factory(3)->create(['post_id' => $post->id]);

            foreach ($comments as $comment) {
                TokenLog::factory()->create([
                    'user_id' => $owner->id,
                    'type' => 'reply',
                    'reference_id' => $comment->id,
                    'tokens_used' => rand(50, 200),
                ]);
            }
        }

        ImageJob::factory(rand(2, 5))->create(['user_id' => $owner->id]);
    }

    /**
     * Struttura di default dei canali, replica di Users::get_channels() v1
     * (v1-reference/app/Http/Controllers/Users.php) — il controller utenti
     * non è stato ancora portato in v2.
     */
    private function defaultChannels(): array
    {
        $channel = static fn (string $name, string $cssClass) => [
            'name' => $name,
            'css_class' => $cssClass,
            'id' => null,
            'on' => null,
            'reply_on' => null,
            'reply_n' => null,
            'options' => [],
        ];

        return [
            'facebook' => $channel('Facebook', 'fa-brands fa-facebook'),
            'instagram' => $channel('Instagram', 'fa-brands fa-instagram'),
            'linkedin' => $channel('LinkedIn', 'fa-brands fa-linkedin'),
            'wordpress' => $channel('WordPress', 'fa-brands fa-wordpress-simple'),
            'newsletter' => $channel('Newsletter', 'fa-regular fa-envelope'),
        ];
    }
}
