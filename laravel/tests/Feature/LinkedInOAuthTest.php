<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkedInOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_requires_client_id_and_secret_to_be_configured(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id]);

        $this->actingAs($admin)
            ->get(route('linkedin.redirect', $child))
            ->assertStatus(422);
    }

    public function test_redirect_sends_the_owning_manager_to_linkedin_with_state_in_session(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create([
            'user_id' => $child->id,
            'linkedin_client_id' => 'client-abc',
            'linkedin_client_secret' => 'secret-abc',
        ]);

        $response = $this->actingAs($admin)->get(route('linkedin.redirect', $child));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization?', $response->headers->get('Location'));
        $this->assertSame($child->id, session('linkedin_oauth')['user_id']);
    }

    public function test_manager_cannot_start_oauth_for_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);
        Settings::factory()->create([
            'user_id' => $childOfB->id,
            'linkedin_client_id' => 'client-abc',
            'linkedin_client_secret' => 'secret-abc',
        ]);

        $this->actingAs($managerA)
            ->get(route('linkedin.redirect', $childOfB))
            ->assertForbidden();
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        session(['linkedin_oauth' => ['state' => 'expected-state', 'user_id' => $admin->id]]);

        $response = $this->actingAs($admin)->get(route('linkedin.callback', ['state' => 'wrong-state', 'code' => 'xyz']));

        $response->assertRedirect();
        $this->assertNull(session('linkedin_oauth'));
    }

    public function test_successful_callback_propagates_token_to_every_account_sharing_the_same_app(): void
    {
        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
                'access_token' => 'fresh-token-123',
                'expires_in' => 5184000,
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $childA = User::factory()->create(['parent_id' => $admin->id]);
        $childB = User::factory()->create(['parent_id' => $admin->id]);
        $unrelated = User::factory()->create(['parent_id' => $admin->id]);

        Settings::factory()->create(['user_id' => $childA->id, 'linkedin_client_id' => 'shared-app', 'linkedin_client_secret' => 'shared-secret', 'linkedin_token' => 'old']);
        Settings::factory()->create(['user_id' => $childB->id, 'linkedin_client_id' => 'shared-app', 'linkedin_client_secret' => 'shared-secret', 'linkedin_token' => 'old']);
        Settings::factory()->create(['user_id' => $unrelated->id, 'linkedin_client_id' => 'other-app', 'linkedin_client_secret' => 'other-secret', 'linkedin_token' => 'untouched']);

        session(['linkedin_oauth' => ['state' => 'good-state', 'user_id' => $childA->id]]);

        $this->actingAs($admin)
            ->get(route('linkedin.callback', ['state' => 'good-state', 'code' => 'auth-code']))
            ->assertRedirect(route('account.edit', $childA));

        $this->assertSame('fresh-token-123', $childA->settings->fresh()->linkedin_token);
        $this->assertSame('fresh-token-123', $childB->settings->fresh()->linkedin_token);
        $this->assertSame('untouched', $unrelated->settings->fresh()->linkedin_token);
        $this->assertNotNull($childA->settings->fresh()->linkedin_token_expires_at);
    }
}
