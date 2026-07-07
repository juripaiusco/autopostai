<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledPost(User $owner, Carbon $at): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true]],
            'published' => '0',
            'published_at' => $at,
        ]);
    }

    public function test_shows_only_scheduled_posts_within_the_requested_month(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $thisMonth = $this->scheduledPost($admin, now()->addDays(3));
        $nextMonth = $this->scheduledPost($admin, now()->addMonthNoOverflow()->addDays(3));
        $alreadyPublished = Post::factory()->create([
            'user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true]],
            'published' => '1',
            'published_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($admin)->get(route('schedule'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Schedule')
            ->has('posts', 1)
            ->where('posts.0.id', $thisMonth->id)
        );
    }

    public function test_manager_only_sees_scheduled_posts_of_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $ownChild = User::factory()->create(['parent_id' => $manager->id]);
        $strangerChild = User::factory()->create(['parent_id' => $admin->id]);

        $ownPost = $this->scheduledPost($ownChild, now()->addDays(1));
        $this->scheduledPost($strangerChild, now()->addDays(1));

        $response = $this->actingAs($manager)->get(route('schedule'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('posts', 1)
            ->where('posts.0.id', $ownPost->id)
        );
    }

    public function test_navigating_to_next_month_reflects_in_prev_next_labels(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $currentMonth = now()->format('Y-m');
        $expectedNext = now()->addMonthNoOverflow()->format('Y-m');

        $response = $this->actingAs($admin)->get(route('schedule', ['month' => $currentMonth]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', $currentMonth)
            ->where('nextMonth', $expectedNext)
        );
    }
}
