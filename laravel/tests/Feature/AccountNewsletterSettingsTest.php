<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountNewsletterSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_account_persists_smtp_and_renamed_provider_fields(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id]);

        $this->actingAs($admin)
            ->put(route('account.update', $child), [
                'name' => $child->name,
                'email' => $child->email,
                'newsletter' => [
                    'mailchimp' => ['apiKey' => 'mc-key', 'serverPrefix' => 'us1', 'audienceId' => 'aud-1'],
                    'brevo' => ['apiKey' => 'brevo-key', 'listId' => 'list-1', 'sender' => 'noreply@example.com'],
                    'smtp' => [
                        'host' => 'smtp.example.com',
                        'port' => '587',
                        'username' => 'smtp-user',
                        'password' => 'smtp-pass',
                        'encryption' => 'tls',
                        'sender' => 'newsletter@example.com',
                    ],
                    'template' => [
                        'content' => '<html><body>{{ post }}</body></html>',
                        'cta' => '<a href="{{ link }}">Scopri di più</a>',
                    ],
                ],
            ])
            ->assertRedirect();

        $settings = $child->settings()->first();

        $this->assertSame('mc-key', $settings->nl_mailchimp_api);
        $this->assertSame('us1', $settings->nl_mailchimp_datacenter);
        $this->assertSame('aud-1', $settings->nl_mailchimp_list_id);
        $this->assertSame('brevo-key', $settings->nl_brevo_api);
        $this->assertSame('list-1', $settings->nl_brevo_list_id);
        $this->assertSame('noreply@example.com', $settings->nl_brevo_from_email);

        $this->assertSame('smtp.example.com', $settings->nl_smtp_host);
        $this->assertSame('587', $settings->nl_smtp_port);
        $this->assertSame('smtp-user', $settings->nl_smtp_username);
        $this->assertSame('smtp-pass', $settings->nl_smtp_password);
        $this->assertSame('tls', $settings->nl_smtp_encryption);
        $this->assertSame('newsletter@example.com', $settings->nl_smtp_sender);

        $this->assertSame('<html><body>{{ post }}</body></html>', $settings->nl_template);
        $this->assertSame('<a href="{{ link }}">Scopri di più</a>', $settings->nl_template_cta);

        $this->actingAs($admin)
            ->get(route('account.edit', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.newsletter.smtp.host', 'smtp.example.com')
                ->where('account.newsletter.smtp.connected', true)
                ->where('account.newsletter.template.content', '<html><body>{{ post }}</body></html>')
                ->where('account.newsletter.template.cta', '<a href="{{ link }}">Scopri di più</a>')
            );
    }
}
