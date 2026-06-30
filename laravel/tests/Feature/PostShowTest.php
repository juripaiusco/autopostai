<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostShowTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $owner, array $overrides = []): Post
    {
        return Post::factory()->create(array_merge([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true], 'instagram' => ['on' => true]],
            'published' => '1',
            'comments_enabled' => '1',
            'auto_reply_enabled' => '1',
        ], $overrides));
    }

    public function test_owner_can_view_their_own_post(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->makePost($owner);

        TokenLog::factory()->create(['user_id' => $owner->id, 'type' => 'post', 'reference_id' => $post->id, 'tokens_used' => 120]);

        Comment::factory()->create(['post_id' => $post->id, 'channel' => 'facebook', 'reply' => 'Grazie!']);
        Comment::factory()->create(['post_id' => $post->id, 'channel' => 'instagram', 'reply' => null, 'reply_id' => null, 'reply_created_time' => null]);

        $response = $this->actingAs($owner)->get(route('posts.show', $post));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Show')
            ->where('post.id', $post->id)
            ->where('post.status', 'published')
            ->where('post.commentsTotal', 2)
            ->where('post.totalTokens', 120)
            ->has('comments', 2)
        );
    }

    public function test_other_normal_user_cannot_view_post(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->makePost($owner);

        $this->actingAs($stranger)->get(route('posts.show', $post))->assertForbidden();
    }

    public function test_admin_can_view_any_users_post(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->makePost($owner);

        $this->actingAs($admin)->get(route('posts.show', $post))->assertOk();
    }

    public function test_comments_by_channel_match_real_counts(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->makePost($owner);

        Comment::factory()->count(2)->create(['post_id' => $post->id, 'channel' => 'facebook']);
        Comment::factory()->count(3)->create(['post_id' => $post->id, 'channel' => 'instagram']);

        $response = $this->actingAs($owner)->get(route('posts.show', $post));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('post.commentsByChannel.facebook', 2)
            ->where('post.commentsByChannel.instagram', 3)
            ->where('post.commentsTotal', 5)
        );
    }
}
