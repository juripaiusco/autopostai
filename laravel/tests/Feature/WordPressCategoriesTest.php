<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WordPressCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_wordpress_url_to_be_configured(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id]);

        $this->actingAs($admin)
            ->post(route('wordpress.categories', $child))
            ->assertStatus(422);
    }

    public function test_manager_cannot_fetch_categories_for_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);
        Settings::factory()->create(['user_id' => $childOfB->id, 'wordpress_url' => 'https://example.com']);

        $this->actingAs($managerA)
            ->post(route('wordpress.categories', $childOfB))
            ->assertForbidden();
    }

    public function test_fetches_and_persists_categories_from_the_configured_site(): void
    {
        Http::fake([
            'https://example.com/wp-json/wp/v2/categories*' => Http::response([
                ['id' => 8, 'name' => 'Novità'],
                ['id' => 12, 'name' => 'Ricette'],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id, 'wordpress_url' => 'https://example.com']);

        $this->actingAs($admin)
            ->post(route('wordpress.categories', $child))
            ->assertRedirect();

        $categories = $child->settings->fresh()->wordpress_options['categories'];
        $this->assertSame([
            ['id' => '8', 'name' => 'Novità'],
            ['id' => '12', 'name' => 'Ricette'],
        ], $categories);

        $this->actingAs($admin)
            ->get(route('account.edit', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account.wordpress.categories', 2)
                ->where('account.wordpress.categories.0.name', 'Novità')
            );
    }

    public function test_shows_error_toast_when_wordpress_site_does_not_respond_correctly(): void
    {
        Http::fake([
            'https://broken-site.test/wp-json/wp/v2/categories*' => Http::response(null, 500),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id, 'wordpress_url' => 'https://broken-site.test']);

        $this->actingAs($admin)
            ->post(route('wordpress.categories', $child))
            ->assertSessionHas('toast', fn ($msg) => str_contains($msg, 'non ha risposto'));

        $this->assertNull($child->settings->fresh()->wordpress_options);
    }
}
