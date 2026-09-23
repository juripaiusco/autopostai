<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * openai_api_key e meta_page_id sono unique su settings: una chiave o una
 * pagina già usata da un altro account deve dare un errore leggibile, non un
 * 500 del vincolo DB.
 */
class AccountUniqueSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): array
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $first = User::factory()->create(['parent_id' => $admin->id]);
        $second = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create(['user_id' => $first->id, 'openai_api_key' => 'sk-shared', 'meta_page_id' => '555']);
        Settings::factory()->create(['user_id' => $second->id, 'openai_api_key' => null, 'meta_page_id' => null]);

        return [$admin, $first, $second];
    }

    private function save(User $admin, User $user, array $payload)
    {
        return $this->actingAs($admin)->put(route('account.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
        ] + $payload);
    }

    public function test_openai_key_of_another_account_is_a_validation_error(): void
    {
        [$admin, , $second] = $this->accounts();

        $this->save($admin, $second, ['openai' => ['apiKey' => 'sk-shared']])
            ->assertSessionHasErrors(['openai.apiKey' => 'Questa chiave OpenAI è già collegata a un altro account.']);

        $this->assertNull($second->settings()->first()->openai_api_key);
    }

    public function test_meta_page_of_another_account_is_a_validation_error(): void
    {
        [$admin, , $second] = $this->accounts();

        $this->save($admin, $second, ['meta' => ['pageId' => '555']])
            ->assertSessionHasErrors(['meta.pageId' => 'Questa pagina Meta è già collegata a un altro account.']);
    }

    public function test_account_can_keep_its_own_values(): void
    {
        [$admin, $first] = $this->accounts();

        $this->save($admin, $first, ['openai' => ['apiKey' => 'sk-shared'], 'meta' => ['pageId' => '555']])
            ->assertSessionHasNoErrors();

        $this->assertSame('555', $first->settings()->first()->meta_page_id);
    }
}
