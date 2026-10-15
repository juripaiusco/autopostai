<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostIndexCoverTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $owner, ?array $img): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true]],
            'img' => $img,
        ]);
    }

    public function test_list_exposes_first_image_as_cover(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, ['a.jpg', 'b.jpg']);

        $this->actingAs($user)->get(route('posts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.data.0.cover', fn ($url) => str_ends_with($url, "/storage/posts/{$post->id}/a.jpg"))
                ->where('posts.data.0.thumb', $post->thumbGradient())
            );
    }

    public function test_post_without_images_has_null_cover_and_gradient_fallback(): void
    {
        $user = User::factory()->create();
        $this->makePost($user, null);

        $this->actingAs($user)->get(route('posts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.data.0.cover', null)
                ->where('posts.data.0.thumb', fn ($thumb) => str_starts_with($thumb, 'linear-gradient('))
            );
    }

    public function test_dashboard_recent_posts_expose_cover(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, ['cover.png']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentPosts.0.cover', fn ($url) => str_ends_with($url, "/storage/posts/{$post->id}/cover.png"))
            );
    }
}
