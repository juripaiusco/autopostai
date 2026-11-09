<?php

namespace Tests\Feature;

use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Barra "Crediti utilizzati" (prop condivise usage/showTokens): consumo AI del
 * mese dell'utente filtrato o il proprio; admin senza filtro = niente barra,
 * token veri; in tutti gli altri casi crediti.
 */
class CreditsUsageTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $user, int $tokens, string $type = 'post', $createdAt = null): void
    {
        $log = TokenLog::create(['user_id' => $user->id, 'type' => $type, 'reference_id' => 1, 'tokens_used' => $tokens]);
        if ($createdAt) {
            $log->forceFill(['created_at' => $createdAt])->save();
        }
    }

    public function test_simple_user_sees_own_monthly_usage(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'tokens_limit' => 10000]);
        $this->log($user, 2000);
        $this->log($user, 500, 'preview');
        $this->log($user, 500, 'reply');
        $this->log($user, 9000, 'post', now()->subMonthNoOverflow()); // mese scorso: non conta

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.used', 3000)
                ->where('usage.limit', 10000)
                ->where('usage.percent', 30)
                ->where('showTokens', false));
    }

    public function test_percent_is_capped_and_empty_limit_means_full(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $over = User::factory()->create(['parent_id' => $admin->id, 'tokens_limit' => 1000]);
        $this->log($over, 1500);
        $blocked = User::factory()->create(['parent_id' => $admin->id, 'tokens_limit' => 0]);

        $this->actingAs($over)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('usage.percent', 100));
        $this->actingAs($blocked)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('usage.percent', 100));
    }

    public function test_admin_without_scope_has_no_bar_and_sees_tokens(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage', null)
                ->where('showTokens', true));
    }

    public function test_admin_scoped_on_a_user_sees_that_user_in_credits(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'tokens_limit' => 4000]);
        $this->log($user, 1000);

        $this->actingAs($admin)->withSession(['scoped_user_id' => $user->id])->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.used', 1000)
                ->where('usage.percent', 25)
                ->where('showTokens', false));
    }

    public function test_manager_sees_own_usage_or_the_scoped_child(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1, 'tokens_limit' => 1000]);
        $child = User::factory()->create(['parent_id' => $manager->id, 'tokens_limit' => 2000]);
        $this->log($manager, 100);
        $this->log($child, 1000);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.percent', 10)
                ->where('showTokens', false));

        $this->actingAs($manager)->withSession(['scoped_user_id' => $child->id])->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.percent', 50)
                ->where('showTokens', false));
    }
}
