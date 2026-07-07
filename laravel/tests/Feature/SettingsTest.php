<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_user_sees_ai_tab_data(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        Settings::factory()->create([
            'user_id' => $user->id,
            'ai_personality' => 'Assistente gentile',
            'ai_prompt_prefix' => 'Orari: 9-18',
            'ai_comment_prefix' => 'Rispondi con tono amichevole',
        ]);

        $response = $this->actingAs($user)->get(route('settings'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->where('isSimpleUser', true)
            ->where('ai.profile', 'Assistente gentile')
        );
    }

    public function test_admin_does_not_see_ai_tab_data(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);

        $response = $this->actingAs($admin)->get(route('settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('isSimpleUser', false)
            ->where('ai', null)
        );
    }

    public function test_simple_user_can_update_own_profile_and_ai_fields(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'name' => 'Old Name', 'email' => 'old@example.com']);

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'password' => 'newpassword123',
            'ai' => ['profile' => 'Nuovo profilo AI', 'knows' => 'Sa tutto', 'commentStyle' => 'Formale'],
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertSame('Nuovo profilo AI', $user->settings->ai_personality);
    }

    public function test_admin_updating_profile_cannot_smuggle_ai_fields(): void
    {
        $admin = User::factory()->create(['parent_id' => null, 'name' => 'Admin', 'email' => 'admin@example.com']);

        $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Admin Renamed',
            'email' => 'admin@example.com',
            'ai' => ['profile' => 'Non dovrebbe salvarsi', 'knows' => '', 'commentStyle' => ''],
        ])->assertRedirect();

        $admin->refresh();
        $this->assertSame('Admin Renamed', $admin->name);
        $this->assertNull($admin->settings);
    }

    public function test_password_left_blank_does_not_change_it(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $originalHash = $user->password;

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
        ])->assertRedirect();

        $this->assertSame($originalHash, $user->fresh()->password);
    }
}
