<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_register_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_registering_creates_a_new_admin_and_logs_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Nuovo Studio',
            'email' => 'nuovo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::whereEmail('nuovo@example.com')->firstOrFail();
        $this->assertNull($user->parent_id);
        $this->assertTrue($user->isAdmin());
    }

    public function test_guest_can_view_forgot_password_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_requesting_reset_link_sends_notification(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['parent_id' => null]);

        $this->post(route('password.email'), ['email' => $admin->email])->assertRedirect();

        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_guest_can_view_reset_password_page_with_token_and_email(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $token = Password::createToken($admin);

        $this->get(route('password.reset', ['token' => $token, 'email' => $admin->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $admin->email)
                ->where('token', $token)
            );
    }

    public function test_submitting_valid_token_resets_the_password(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $token = Password::createToken($admin);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'brandnewpass',
            'password_confirmation' => 'brandnewpass',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brandnewpass', $admin->fresh()->password));
    }
}
