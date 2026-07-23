<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostChannelFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_user_can_fetch_wordpress_categories_for_their_own_post(): void
    {
        Http::fake([
            'https://example.com/wp-json/wp/v2/categories*' => Http::response([
                ['id' => 8, 'name' => 'Novità'],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $user->id, 'wordpress_url' => 'https://example.com']);

        $this->actingAs($user)
            ->getJson(route('posts.wordpress-categories', $user))
            ->assertOk()
            ->assertJson(['categories' => [['id' => '8', 'name' => 'Novità']]]);
    }

    public function test_manager_cannot_fetch_wordpress_categories_for_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);
        Settings::factory()->create(['user_id' => $childOfB->id, 'wordpress_url' => 'https://example.com']);

        $this->actingAs($managerA)
            ->getJson(route('posts.wordpress-categories', $childOfB))
            ->assertForbidden();
    }

    public function test_wordpress_fetch_for_post_requires_url_configured(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        Settings::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->getJson(route('posts.wordpress-categories', $admin))
            ->assertStatus(422);
    }

    public function test_simple_user_can_fetch_newsletter_lists_for_their_own_post(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/contacts/lists*' => Http::response([
                'lists' => [['id' => 5, 'name' => 'Iscritti']],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $user->id, 'nl_brevo_api' => 'fake-key']);

        $this->actingAs($user)
            ->getJson(route('posts.newsletter-lists', $user))
            ->assertOk()
            ->assertJson(['provider' => 'brevo', 'lists' => [['id' => '5', 'name' => 'Iscritti']]]);
    }

    public function test_manager_can_fetch_lists_for_their_own_child(): void
    {
        Http::fake([
            'https://us1.api.mailchimp.com/3.0/lists*' => Http::response([
                'lists' => [['id' => 'x1', 'name' => 'Lista base']],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $child = User::factory()->create(['parent_id' => $manager->id]);
        Settings::factory()->create(['user_id' => $child->id, 'nl_mailchimp_api' => 'fake-key', 'nl_mailchimp_datacenter' => 'us1']);

        $this->actingAs($manager)
            ->getJson(route('posts.newsletter-lists', $child))
            ->assertOk()
            ->assertJson(['provider' => 'mailchimp']);
    }
}
