<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsletterListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_mailchimp_or_brevo_to_be_configured(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id]);

        $this->actingAs($admin)
            ->post(route('newsletter.lists', $child))
            ->assertStatus(422);
    }

    public function test_manager_cannot_fetch_lists_for_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);
        Settings::factory()->create(['user_id' => $childOfB->id, 'nl_mailchimp_api' => 'key-b']);

        $this->actingAs($managerA)
            ->post(route('newsletter.lists', $childOfB))
            ->assertForbidden();
    }

    public function test_fetches_mailchimp_lists_when_mailchimp_is_configured(): void
    {
        Http::fake([
            'https://us21.api.mailchimp.com/3.0/lists*' => Http::response([
                'lists' => [
                    ['id' => 'abc123', 'name' => 'Clienti VIP'],
                    ['id' => 'def456', 'name' => 'Newsletter generale'],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create([
            'user_id' => $child->id,
            'nl_mailchimp_api' => 'fake-key',
            'nl_mailchimp_datacenter' => 'us21',
        ]);

        $this->actingAs($admin)
            ->post(route('newsletter.lists', $child))
            ->assertRedirect();

        $lists = $child->settings->fresh()->nl_mailchimp_options['lists'];
        $this->assertSame([
            ['id' => 'abc123', 'name' => 'Clienti VIP'],
            ['id' => 'def456', 'name' => 'Newsletter generale'],
        ], $lists);

        $this->actingAs($admin)
            ->get(route('account.edit', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->has('account.newsletter.mailchimp.lists', 2)
            );
    }

    public function test_falls_back_to_brevo_when_mailchimp_is_not_configured(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/contacts/lists*' => Http::response([
                'lists' => [
                    ['id' => 12, 'name' => 'Lista principale'],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id, 'nl_brevo_api' => 'fake-brevo-key']);

        $this->actingAs($admin)
            ->post(route('newsletter.lists', $child))
            ->assertRedirect();

        $this->assertSame(
            [['id' => '12', 'name' => 'Lista principale']],
            $child->settings->fresh()->nl_brevo_options['lists']
        );
    }

    public function test_shows_error_toast_when_provider_does_not_respond_correctly(): void
    {
        Http::fake([
            'https://us5.api.mailchimp.com/3.0/lists*' => Http::response(null, 401),
        ]);

        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create([
            'user_id' => $child->id,
            'nl_mailchimp_api' => 'bad-key',
            'nl_mailchimp_datacenter' => 'us5',
        ]);

        $this->actingAs($admin)
            ->post(route('newsletter.lists', $child))
            ->assertSessionHas('toast', fn ($msg) => str_contains($msg, 'non ha risposto'));
    }
}
