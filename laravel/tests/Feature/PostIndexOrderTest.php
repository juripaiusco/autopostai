<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostIndexOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Post $draft;
    private Post $published;
    private Post $scheduled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['parent_id' => null]);

        $make = fn (array $attrs) => Post::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
            'channels' => ['facebook' => ['on' => true]],
        ], $attrs));

        $this->published = $make(['title' => 'A pubblicato', 'published' => '1', 'published_at' => now()->subDay()]);
        $this->scheduled = $make(['title' => 'B programmato', 'published' => '0', 'published_at' => now()->addDay()]);
        $this->draft = $make(['title' => 'C bozza', 'published' => '0', 'published_at' => null]);
    }

    private function ids(array $query): array
    {
        $ids = [];

        $this->actingAs($this->user)
            ->get(route('posts', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['posts']['data'])->pluck('id')->all();
            });

        return $ids;
    }

    public function test_drafts_come_first_with_default_date_sort(): void
    {
        $this->assertSame(
            [$this->draft->id, $this->scheduled->id, $this->published->id],
            $this->ids([]),
        );
    }

    public function test_drafts_stay_first_with_ascending_date_sort(): void
    {
        $this->assertSame(
            [$this->draft->id, $this->published->id, $this->scheduled->id],
            $this->ids(['sort' => 'publishedAt', 'dir' => 'asc']),
        );
    }

    public function test_title_sort_does_not_pin_drafts(): void
    {
        $this->assertSame(
            [$this->published->id, $this->scheduled->id, $this->draft->id],
            $this->ids(['sort' => 'title', 'dir' => 'asc']),
        );
    }

    public function test_dashboard_recent_posts_show_drafts_first(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recentPosts.0.id', $this->draft->id)
                ->where('recentPosts.1.id', $this->scheduled->id));
    }
}
