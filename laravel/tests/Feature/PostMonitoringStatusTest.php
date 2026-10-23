<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Post pubblicato e ancora seguito dal worker per i commenti (task_complete=0)
 * vs monitoraggio chiuso (task_complete=1 => "Completato").
 */
class PostMonitoringStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $owner, array $overrides = []): Post
    {
        return Post::factory()->create(array_merge([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true, 'id' => '111_222']],
            'published' => '1',
            'task_complete' => '0',
            'published_at' => now()->subDays(5),
        ], $overrides));
    }

    public function test_published_post_still_monitored_exposes_monitoring_window(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);
        $until = $post->published_at->copy()->addDays(Post::COMMENT_MONITORING_DAYS)->toIso8601String();

        $this->assertSame('published', $post->status());

        $this->actingAs($user)->get(route('posts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.data.0.status', 'published')
                ->where('posts.data.0.monitoringUntil', $until)
            );

        $this->actingAs($user)->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.status', 'published')
                ->where('post.monitoringUntil', $until)
                ->where('post.channelStatus.facebook', 'published')
            );
    }

    public function test_completed_post_is_done_without_monitoring_and_not_editable(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, ['task_complete' => '1']);

        $this->assertSame('done', $post->status());
        $this->assertNull($post->monitoringUntil());
        $this->assertFalse($post->isEditable());

        $this->actingAs($user)->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.status', 'done')
                ->where('post.monitoringUntil', null)
                ->where('post.channelStatus.facebook', 'done')
            );
    }

    public function test_unpublished_posts_have_no_monitoring_window(): void
    {
        $user = User::factory()->create();

        $draft = $this->makePost($user, ['published' => '0', 'published_at' => null, 'channels' => ['facebook' => ['on' => true]]]);
        $scheduled = $this->makePost($user, ['published' => '0', 'published_at' => now()->addDay(), 'channels' => ['facebook' => ['on' => true]]]);

        $this->assertNull($draft->monitoringUntil());
        $this->assertNull($scheduled->monitoringUntil());
    }
}
