<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function postFor(User $owner, string $title): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'title' => $title,
            'channels' => ['facebook' => ['on' => true]],
        ]);
    }

    public function test_admin_finds_posts_and_accounts_by_query(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id, 'name' => 'Mario Rossi']);
        $this->postFor($owner, 'Lancio nuovo prodotto');
        $this->postFor($owner, 'Altro post');

        $response = $this->actingAs($admin)->getJson('/search?q=lancio');
        $response->assertOk();
        $response->assertJsonCount(1, 'posts');
        $response->assertJsonPath('posts.0.title', 'Lancio nuovo prodotto');

        $response = $this->actingAs($admin)->getJson('/search?q=rossi');
        $response->assertJsonCount(1, 'accounts');
        $response->assertJsonPath('accounts.0.name', 'Mario Rossi');
    }

    public function test_simple_user_only_finds_own_posts_and_no_accounts(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id, 'name' => 'Luca Bianchi']);
        $this->postFor($user, 'Post di prova mio');
        $this->postFor($stranger, 'Post di prova altrui');

        $response = $this->actingAs($user)->getJson('/search?q=prova');
        $response->assertJsonCount(1, 'posts');
        $response->assertJsonPath('posts.0.title', 'Post di prova mio');

        $response = $this->actingAs($user)->getJson('/search?q=bianchi');
        $response->assertJsonCount(0, 'accounts');
    }

    public function test_manager_only_finds_accounts_among_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        User::factory()->create(['parent_id' => $manager->id, 'name' => 'Anna Verdi']);
        User::factory()->create(['parent_id' => $admin->id, 'name' => 'Anna Estranea']);

        $response = $this->actingAs($manager)->getJson('/search?q=anna');
        $response->assertJsonCount(1, 'accounts');
        $response->assertJsonPath('accounts.0.name', 'Anna Verdi');
    }

    public function test_empty_query_returns_empty_results(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);

        $response = $this->actingAs($admin)->getJson('/search?q=');
        $response->assertJson(['posts' => [], 'accounts' => []]);
    }
}
