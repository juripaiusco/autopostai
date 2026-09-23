<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cancellazione di un account è in cascata (sotto-account, post, contatti,
 * impostazioni) e isAdmin() si deduce da parent_id === null: senza guardie un
 * admin poteva eliminare sé stesso o un manager con tutti i suoi clienti, e
 * declassarsi da solo assegnandosi un manager.
 */
class AccountHierarchyGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);

        $this->actingAs($admin)->delete(route('account.destroy', $admin))
            ->assertSessionHasErrors('account');

        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_delete_another_admin(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $other = User::factory()->create(['parent_id' => null]);

        $this->actingAs($admin)->delete(route('account.destroy', $other))
            ->assertSessionHasErrors('account');

        $this->assertModelExists($other);
    }

    public function test_account_with_subaccounts_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $client = User::factory()->create(['parent_id' => $manager->id]);
        $post = Post::factory()->create(['user_id' => $client->id, 'created_by_user_id' => $client->id, 'channels' => ['facebook' => ['on' => true]]]);

        $this->actingAs($admin)->delete(route('account.destroy', $manager))
            ->assertSessionHasErrors('account');

        $this->assertModelExists($manager);
        $this->assertModelExists($client);
        $this->assertModelExists($post);
    }

    public function test_leaf_account_can_still_be_deleted(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $client = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($admin)->delete(route('account.destroy', $client))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($client);
    }

    public function test_admin_editing_own_account_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);

        $this->actingAs($admin)->put(route('account.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'canSubusers' => false,
            'manager' => $manager->id,
        ])->assertRedirect();

        $this->assertNull($admin->fresh()->parent_id);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_manager_with_subaccounts_keeps_the_role(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        User::factory()->create(['parent_id' => $manager->id]);

        $this->actingAs($admin)->put(route('account.update', $manager), [
            'name' => $manager->name,
            'email' => $manager->email,
            'canSubusers' => false,
        ])->assertSessionHasErrors('canSubusers');

        $this->assertEquals(1, $manager->fresh()->child_on);
    }
}
