<?php

namespace Tests\Feature;

use App\Console\Commands\ImportV1Data;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * v1:import deve portare `channels` nella forma v2. Copiato com'era ("on": "1"
 * stringa) il worker Python leggeva ogni canale come spento: i post
 * programmati in v1 venivano marcati pubblicati senza uscire, quelli già
 * pubblicati non venivano più tolti dai canali né monitorati.
 */
class ImportV1DataTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY = 'mariadb_legacy';

    protected function setUp(): void
    {
        parent::setUp();

        // DB v1 finto: SQLite in memoria al posto della connessione MariaDB legacy.
        config(['database.connections.'.self::LEGACY => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge(self::LEGACY);

        $legacy = Schema::connection(self::LEGACY);

        foreach (['users', 'token_logs', 'image_jobs', 'push_subscriptions', 'push_notifications'] as $table) {
            $legacy->create($table, fn (Blueprint $t) => $t->id());
        }

        $legacy->create('settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            foreach ([
                'ai_personality', 'ai_prompt_prefix', 'ai_comment_prefix', 'openai_api_key', 'meta_page_id',
                'linkedin_person_id', 'linkedin_company_id', 'linkedin_client_id', 'linkedin_client_secret', 'linkedin_token',
                'wordpress_url', 'wordpress_username', 'wordpress_password', 'wordpress_cat_id', 'wordpress_options',
                'mailchimp_api', 'mailchimp_datacenter', 'mailchimp_list_id', 'mailchimp_from_name', 'mailchimp_from_email',
                'mailchimp_template', 'mailchimp_template_cta', 'mailchimp_options',
                'brevo_api', 'brevo_list_id', 'brevo_from_name', 'brevo_from_email',
                'brevo_template', 'brevo_template_cta', 'brevo_options',
            ] as $column) {
                $t->text($column)->nullable();
            }
            $t->timestamps();
        });

        $legacy->create('posts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('created_by_user_id');
            $t->string('title');
            $t->text('ai_prompt_post')->nullable();
            $t->text('ai_content')->nullable();
            $t->text('ai_prompt_comment')->nullable();
            $t->text('img')->nullable();
            $t->string('img_ai_check_on')->default('0');
            $t->text('channels');
            $t->string('preview')->default('0');
            $t->dateTime('published_at')->nullable();
            $t->string('published')->default('0');
            $t->string('task_complete')->default('0');
            $t->integer('check_attempts')->default(0);
            $t->dateTime('on_hold_until')->nullable();
            $t->string('updated')->default('0');
            $t->string('deleted')->default('0');
            $t->dateTime('deleted_at')->nullable();
            $t->timestamps();
        });

        $legacy->create('comments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->string('channel');
            $t->string('from_id')->nullable();
            $t->string('from_name')->nullable();
            $t->string('message_id')->nullable();
            $t->text('message')->nullable();
            $t->dateTime('message_created_time')->nullable();
            $t->string('reply_id')->nullable();
            $t->text('reply')->nullable();
            $t->dateTime('reply_created_time')->nullable();
            $t->timestamps();
        });
    }

    /** Canale v1 come lo salvava il form v1 (flag stringa, id nullo finché non pubblicato). */
    private function v1Channel(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name, 'css_class' => 'fa', 'id' => null,
            'on' => null, 'reply_on' => null, 'reply_n' => null, 'options' => [],
        ], $overrides);
    }

    private function v1Channels(array $overrides = []): array
    {
        return array_merge([
            'facebook' => $this->v1Channel('Facebook'),
            'instagram' => $this->v1Channel('Instagram'),
            'linkedin' => $this->v1Channel('LinkedIn'),
            'wordpress' => $this->v1Channel('WordPress'),
            'newsletter' => $this->v1Channel('Newsletter'),
        ], $overrides);
    }

    private function legacyPost(User $owner, array $channels, array $overrides = []): int
    {
        return DB::connection(self::LEGACY)->table('posts')->insertGetId(array_merge([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Post v1',
            'channels' => json_encode($channels),
            'published_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function owner(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create(['parent_id' => $admin->id]);
    }

    public function test_scheduled_v1_post_keeps_its_channels_switched_on_for_the_worker(): void
    {
        $owner = $this->owner();
        $id = $this->legacyPost($owner, $this->v1Channels([
            'facebook' => $this->v1Channel('Facebook', ['on' => '1', 'reply_on' => '1', 'reply_n' => '5']),
            'instagram' => $this->v1Channel('Instagram', ['on' => '1']),
        ]));

        $this->artisan('v1:import')->assertSuccessful();

        $post = Post::findOrFail($id);

        // `true` booleano: il worker Python accetta solo quello (is_on).
        $this->assertSame([
            'on' => true, 'comments_enabled' => true, 'auto_reply_enabled' => true, 'reply_n' => 5,
        ], $post->channels['facebook']);
        $this->assertSame([
            'on' => true, 'comments_enabled' => false, 'auto_reply_enabled' => false, 'reply_n' => null,
        ], $post->channels['instagram']);
        $this->assertSame(['on' => false], $post->channels['linkedin']);
        $this->assertSame('1', $post->comments_enabled);
        $this->assertSame('1', $post->auto_reply_enabled);
        $this->assertSame('scheduled', $post->status());
    }

    public function test_published_v1_post_keeps_remote_ids_so_it_is_not_published_again(): void
    {
        $owner = $this->owner();
        $id = $this->legacyPost($owner, $this->v1Channels([
            'facebook' => $this->v1Channel('Facebook', [
                'on' => '1', 'id' => '111_222', 'url' => 'https://facebook.test/111_222',
            ]),
            'wordpress' => $this->v1Channel('WordPress', [
                'on' => '1', 'id' => 44, 'url' => 'https://blog.test/?p=44', 'gallery_html' => '<figure></figure>',
                'options' => ['categories' => [
                    ['id' => 12, 'name' => 'News', 'on' => '1'],
                    ['id' => 9, 'name' => 'Eventi', 'on' => '0'],
                ]],
            ]),
        ]), ['published' => '1', 'published_at' => now()->subDays(3)]);

        $this->artisan('v1:import')->assertSuccessful();

        $post = Post::findOrFail($id);

        $this->assertSame('111_222', $post->channels['facebook']['id']);
        $this->assertSame('https://facebook.test/111_222', $post->channels['facebook']['url']);
        $this->assertSame(44, $post->channels['wordpress']['id']);
        $this->assertSame('<figure></figure>', $post->channels['wordpress']['gallery_html']);
        $this->assertSame([
            ['id' => '12', 'name' => 'News', 'on' => true],
            ['id' => '9', 'name' => 'Eventi', 'on' => false],
        ], $post->channels['wordpress']['categories']);
        $this->assertSame('published', $post->status());
        $this->assertSame('0', $post->comments_enabled);
    }

    public function test_soft_deleted_v1_post_is_marked_deleted_so_the_worker_does_not_remove_it(): void
    {
        $owner = $this->owner();
        $channels = $this->v1Channels([
            'facebook' => $this->v1Channel('Facebook', ['on' => '1', 'id' => '111_222']),
        ]);
        $deletedId = $this->legacyPost($owner, $channels, [
            'published' => '1', 'published_at' => now()->subDays(20),
            'deleted' => '0', 'deleted_at' => now()->subDays(10),
        ]);
        $liveId = $this->legacyPost($owner, $channels, [
            'published' => '1', 'published_at' => now()->subDays(20),
        ]);

        $this->artisan('v1:import')->assertSuccessful();

        $this->assertSame('1', DB::table('posts')->where('id', $deletedId)->value('deleted'));
        $this->assertSame('0', DB::table('posts')->where('id', $liveId)->value('deleted'));
    }

    public function test_newsletter_gets_the_account_provider_and_the_selected_list(): void
    {
        $owner = $this->owner();
        DB::connection(self::LEGACY)->table('settings')->insert([
            'id' => 1, 'user_id' => $owner->id, 'brevo_api' => 'xkeysib-finta', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->legacyPost($owner, $this->v1Channels([
            'newsletter' => $this->v1Channel('Newsletter', [
                'on' => '1',
                'options' => ['lists' => ['lists' => [
                    ['id' => 3, 'name' => 'Clienti', 'on' => '0'],
                    ['id' => 7, 'name' => 'Iscritti', 'on' => '1'],
                ]]],
            ]),
        ]));

        $this->artisan('v1:import')->assertSuccessful();

        $this->assertSame([
            'on' => true,
            'provider' => 'brevo',
            'list' => ['provider' => 'brevo', 'id' => '7', 'name' => 'Iscritti'],
        ], Post::findOrFail($id)->channels['newsletter']);
        $this->assertDatabaseHas('settings', ['user_id' => $owner->id, 'nl_brevo_api' => 'xkeysib-finta']);
    }

    public function test_channels_keyed_by_position_are_matched_by_name(): void
    {
        $channels = ImportV1Data::normalizeChannels(json_encode([
            ['name' => 'Facebook', 'on' => '1', 'id' => null, 'reply_on' => '0', 'reply_n' => null],
            ['name' => 'LinkedIn', 'on' => '0', 'id' => null],
        ]));

        $this->assertTrue($channels['facebook']['on']);
        $this->assertFalse($channels['facebook']['auto_reply_enabled']);
        $this->assertArrayNotHasKey('id', $channels['facebook']); // id nullo: chiave assente, non null
        $this->assertSame(['on' => false], $channels['linkedin']);
        $this->assertSame(['facebook', 'instagram', 'linkedin', 'wordpress', 'newsletter'], array_keys($channels));
    }

    public function test_old_unanswered_v1_comments_leave_the_reply_queue(): void
    {
        $owner = $this->owner();
        $postId = $this->legacyPost($owner, $this->v1Channels([
            'facebook' => $this->v1Channel('Facebook', ['on' => '1', 'reply_on' => '1', 'reply_n' => '5', 'id' => '1_2']),
        ]), ['published' => '1', 'published_at' => now()->subDays(10)]);

        $comment = fn (array $data) => DB::connection(self::LEGACY)->table('comments')->insertGetId(array_merge([
            'post_id' => $postId, 'channel' => 'facebook', 'message' => 'ciao', 'created_at' => now(), 'updated_at' => now(),
        ], $data));

        $old = $comment(['message_id' => 'a', 'message_created_time' => now()->subDays(9)]);
        $recent = $comment(['message_id' => 'b', 'message_created_time' => now()->subHour()]);
        $answered = $comment([
            'message_id' => 'c', 'message_created_time' => now()->subDays(9),
            'reply_id' => 'r1', 'reply' => 'grazie', 'reply_created_time' => now()->subDays(9),
        ]);

        $this->artisan('v1:import')->assertSuccessful();

        $this->assertNotNull(Comment::findOrFail($old)->reply_failed_at);
        $this->assertNull(Comment::findOrFail($recent)->reply_failed_at);
        $this->assertNull(Comment::findOrFail($answered)->reply_failed_at);
    }

    public function test_second_run_reports_rows_as_skipped_not_imported(): void
    {
        $owner = $this->owner();
        $this->legacyPost($owner, $this->v1Channels());

        $this->artisan('v1:import')->expectsOutputToContain('posts: 1/1 righe importate.');
        $this->artisan('v1:import')->expectsOutputToContain('posts: 0/1 righe importate, 1 saltate');

        $this->assertSame(1, Post::count());
    }

    public function test_overdue_unpublished_posts_are_reported_even_in_dry_run(): void
    {
        $owner = $this->owner();
        $overdue = $this->legacyPost($owner, $this->v1Channels(), ['published_at' => now()->subWeek()]);
        $this->legacyPost($owner, $this->v1Channels(), ['published_at' => now()->addWeek()]);

        // Solo il post scaduto compare nell'elenco, non quello programmato.
        $this->artisan('v1:import --dry-run')
            ->expectsOutputToContain("prima di avviarlo. ID: {$overdue}");

        $this->assertSame(0, Post::count());
    }
}
