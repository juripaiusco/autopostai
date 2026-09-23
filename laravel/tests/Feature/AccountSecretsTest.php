<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * I segreti di integrazione non arrivano mai al browser: prima finivano in
 * chiaro nelle props Inertia della pagina Account (visibili a chiunque la
 * aprisse, manager compresi). Campo vuoto in update = "non toccare",
 * `<campo>Clear` = rimuovi.
 */
class AccountSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = [
        'openai_api_key' => 'sk-proj-SECRETopenai9876',
        'linkedin_client_secret' => 'li-SECRETclient5432',
        'linkedin_token' => 'AQV-SECRETtoken1111',
        'wordpress_password' => 'wp-SECRET-pass-2222',
        'nl_mailchimp_api' => 'mc-SECRETkey3333-us21',
        'nl_brevo_api' => 'xkeysib-SECRETbrevo4444',
        'nl_smtp_password' => 'smtp-SECRET-pass-5555',
    ];

    private function makeAccount(): array
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $child = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $child->id] + self::SECRETS);

        return [$admin, $child];
    }

    private function update(User $admin, User $child, array $payload = []): void
    {
        $this->actingAs($admin)
            ->put(route('account.update', $child), array_replace_recursive([
                'name' => $child->name,
                'email' => $child->email,
                'openai' => ['apiKey' => ''],
                'linkedin' => ['clientSecret' => ''],
                'wordpress' => ['password' => ''],
                'newsletter' => [
                    'mailchimp' => ['apiKey' => ''],
                    'brevo' => ['apiKey' => ''],
                    'smtp' => ['password' => ''],
                ],
            ], $payload))
            ->assertRedirect();
    }

    public function test_edit_page_never_contains_plaintext_secrets(): void
    {
        [$admin, $child] = $this->makeAccount();

        $response = $this->actingAs($admin)->get(route('account.edit', $child));

        foreach (self::SECRETS as $secret) {
            $response->assertDontSee($secret, false);
        }

        $response->assertInertia(fn (Assert $page) => $page
            ->where('account.openai.apiKey', '')
            ->where('account.openai.apiKeyHint', '••••9876')
            ->where('account.linkedin.tokenHint', '••••1111')
            ->where('account.newsletter.mailchimp.apiKeyHint', '••••us21')
            // password: nessun frammento, solo "c'è"
            ->where('account.wordpress.passwordHint', '••••••••')
            ->where('account.newsletter.smtp.passwordHint', '••••••••')
            ->where('account.openai.connected', true)
        );
    }

    public function test_blank_secret_fields_keep_the_saved_values(): void
    {
        [$admin, $child] = $this->makeAccount();

        $this->update($admin, $child);

        $settings = $child->settings()->first();
        foreach (self::SECRETS as $column => $value) {
            $this->assertSame($value, $settings->{$column}, $column);
        }
    }

    public function test_a_new_value_replaces_the_secret(): void
    {
        [$admin, $child] = $this->makeAccount();

        $this->update($admin, $child, ['newsletter' => ['smtp' => ['password' => 'nuova-password']]]);

        $settings = $child->settings()->first();
        $this->assertSame('nuova-password', $settings->nl_smtp_password);
        $this->assertSame(self::SECRETS['nl_mailchimp_api'], $settings->nl_mailchimp_api);
    }

    public function test_clear_flag_removes_the_secret(): void
    {
        [$admin, $child] = $this->makeAccount();

        // Es. passaggio da Mailchimp a SMTP: il provider si sceglie da quale
        // chiave e' valorizzata, quindi va rimossa esplicitamente.
        $this->update($admin, $child, ['newsletter' => ['mailchimp' => ['apiKeyClear' => true]]]);

        $settings = $child->settings()->first();
        $this->assertNull($settings->nl_mailchimp_api);
        $this->assertSame(self::SECRETS['nl_brevo_api'], $settings->nl_brevo_api);
    }
}
