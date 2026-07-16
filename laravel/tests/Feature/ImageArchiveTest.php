<?php

namespace Tests\Feature;

use App\Jobs\GenerateImageJob;
use App\Models\ImageJob;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_images_from_the_provider_folder_and_resolves_prompt_when_available(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/with-job.png", 'fake');
        Storage::disk('public')->put("openai/{$user->id}/orphan.png", 'fake');
        Storage::disk('public')->put("openai/{$user->id}/.listing", 'not an image');

        ImageJob::factory()->create([
            'user_id' => $user->id,
            'image_url' => 'with-job.png',
            'prompt' => 'Una pizza margherita',
            'model' => 'gpt-image-1',
        ]);

        $response = $this->actingAs($user)->get(route('posts.image-archive', $user));

        $response->assertOk();
        $images = collect($response->json('images'));

        $this->assertCount(2, $images);
        $this->assertFalse($images->contains('filename', '.listing'));

        $withJob = $images->firstWhere('filename', 'with-job.png');
        $this->assertSame('Una pizza margherita', $withJob['prompt']);

        $orphan = $images->firstWhere('filename', 'orphan.png');
        $this->assertNull($orphan['prompt']);
    }

    public function test_index_returns_the_image_with_prompt_to_the_account_that_generated_it_for_another(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$admin->id}/shared.png", 'fake');

        ImageJob::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $admin->id,
            'image_url' => 'shared.png',
            'prompt' => 'Un gatto astronauta',
            'model' => 'gpt-image-1',
        ]);

        $response = $this->actingAs($admin)->get(route('posts.image-archive', $admin));

        $response->assertOk();
        $image = collect($response->json('images'))->firstWhere('filename', 'shared.png');

        $this->assertNotNull($image);
        $this->assertSame('Un gatto astronauta', $image['prompt']);
    }

    public function test_index_merges_the_actors_own_images_with_the_targets_when_composing_for_a_sub_account(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/target-owned.png", 'fake');
        Storage::disk('public')->put("openai/{$admin->id}/admin-owned.png", 'fake');

        $response = $this->actingAs($admin)->get(route('posts.image-archive', $user));

        $response->assertOk();
        $filenames = collect($response->json('images'))->pluck('filename');

        $this->assertTrue($filenames->contains('target-owned.png'));
        $this->assertTrue($filenames->contains('admin-owned.png'));
    }

    public function test_index_shows_only_own_images_to_a_plain_user_even_if_their_parent_has_others(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/mine.png", 'fake');
        Storage::disk('public')->put("openai/{$admin->id}/not-mine.png", 'fake');

        $response = $this->actingAs($user)->get(route('posts.image-archive', $user));

        $response->assertOk();
        $filenames = collect($response->json('images'))->pluck('filename');

        $this->assertTrue($filenames->contains('mine.png'));
        $this->assertFalse($filenames->contains('not-mine.png'));
    }

    public function test_manager_cannot_view_archive_of_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);

        $this->actingAs($managerA)
            ->get(route('posts.image-archive', $childOfB))
            ->assertForbidden();
    }

    public function test_start_job_creates_a_pending_image_job_and_dispatches_generation(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'image_model_limit' => 5]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test-key']);

        $response = $this->actingAs($user)->postJson(route('posts.image-generate', $user), [
            'prompt' => 'Un gatto astronauta',
            'model' => 'gpt-image-1',
        ]);

        $response->assertOk();
        $jobId = $response->json('job_id');

        $this->assertDatabaseHas('image_jobs', [
            'id' => $jobId,
            'user_id' => $user->id,
            'status' => 'pending',
            'prompt' => 'Un gatto astronauta',
            'model' => 'gpt-image-1',
        ]);

        Queue::assertPushed(GenerateImageJob::class, fn (GenerateImageJob $job) => $job->imageJobId === $jobId);
    }

    public function test_start_job_records_the_acting_user_as_creator_when_generating_for_another_account(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'image_model_limit' => 5]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test-key']);

        $response = $this->actingAs($admin)->postJson(route('posts.image-generate', $user), [
            'prompt' => 'Un gatto astronauta',
            'model' => 'gpt-image-1',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('image_jobs', [
            'id' => $response->json('job_id'),
            'user_id' => $user->id,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_start_job_rejects_an_unknown_model(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'image_model_limit' => 5]);

        $this->actingAs($user)->postJson(route('posts.image-generate', $user), [
            'prompt' => 'Un gatto astronauta',
            'model' => 'not-a-real-model',
        ])->assertStatus(422);
    }

    public function test_start_job_is_blocked_once_the_daily_quota_is_reached(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'image_model_limit' => 1]);
        ImageJob::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('posts.image-generate', $user), [
            'prompt' => 'Un altro gatto',
            'model' => 'gpt-image-1',
        ]);

        $response->assertStatus(422);
        Queue::assertNotPushed(GenerateImageJob::class);
    }

    public function test_start_job_rejects_when_the_account_has_no_openai_key_configured(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'image_model_limit' => 5]);

        $response = $this->actingAs($user)->postJson(route('posts.image-generate', $user), [
            'prompt' => 'Un gatto astronauta',
            'model' => 'gpt-image-1',
        ]);

        $response->assertStatus(422);
        Queue::assertNotPushed(GenerateImageJob::class);
    }

    public function test_status_reports_pending_running_and_completed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $pending = ImageJob::factory()->create(['user_id' => $user->id, 'status' => 'pending', 'image_url' => null]);
        $this->actingAs($user)
            ->getJson(route('posts.image-status', [$user, $pending]))
            ->assertOk()
            ->assertJson(['status' => 'pending']);

        $completed = ImageJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'image_url' => 'done.png',
            'model' => 'gpt-image-1',
        ]);
        Storage::disk('public')->put("openai/{$user->id}/done.png", 'fake');

        $response = $this->actingAs($user)->getJson(route('posts.image-status', [$user, $completed]));
        $response->assertOk();
        $this->assertSame('completed', $response->json('status'));
        $this->assertSame('done.png', $response->json('filename'));
        $this->assertStringContainsString("openai/{$user->id}/done.png", $response->json('url'));
    }

    public function test_manager_cannot_check_status_of_a_job_belonging_to_an_account_they_do_not_own(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);
        $job = ImageJob::factory()->create(['user_id' => $childOfB->id]);

        $this->actingAs($managerA)
            ->getJson(route('posts.image-status', [$childOfB, $job]))
            ->assertForbidden();
    }

    public function test_destroy_deletes_the_file_and_its_image_job(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/with-job.png", 'fake');
        ImageJob::factory()->create(['user_id' => $user->id, 'image_url' => 'with-job.png']);

        $this->actingAs($user)
            ->delete(route('posts.image-archive.destroy', [$user, 'with-job.png']))
            ->assertOk();

        Storage::disk('public')->assertMissing("openai/{$user->id}/with-job.png");
        $this->assertDatabaseMissing('image_jobs', ['user_id' => $user->id, 'image_url' => 'with-job.png']);
    }

    public function test_destroy_removes_both_copies_when_the_image_exists_in_the_target_and_the_actors_folder(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/shared.png", 'fake');
        Storage::disk('public')->put("openai/{$admin->id}/shared.png", 'fake');
        ImageJob::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $admin->id,
            'image_url' => 'shared.png',
        ]);

        $this->actingAs($admin)
            ->delete(route('posts.image-archive.destroy', [$user, 'shared.png']))
            ->assertOk();

        Storage::disk('public')->assertMissing("openai/{$user->id}/shared.png");
        Storage::disk('public')->assertMissing("openai/{$admin->id}/shared.png");
        $this->assertDatabaseMissing('image_jobs', ['user_id' => $user->id, 'image_url' => 'shared.png']);
    }

    public function test_destroy_removes_an_image_that_exists_only_in_the_actors_own_folder_without_touching_the_target(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$admin->id}/admin-owned.png", 'fake');
        Storage::disk('public')->put("openai/{$user->id}/target-owned.png", 'fake');

        $this->actingAs($admin)
            ->delete(route('posts.image-archive.destroy', [$user, 'admin-owned.png']))
            ->assertOk();

        Storage::disk('public')->assertMissing("openai/{$admin->id}/admin-owned.png");
        Storage::disk('public')->assertExists("openai/{$user->id}/target-owned.png");
    }

    public function test_destroy_deletes_an_orphan_file_with_no_image_job(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("openai/{$user->id}/orphan.png", 'fake');

        $this->actingAs($user)
            ->delete(route('posts.image-archive.destroy', [$user, 'orphan.png']))
            ->assertOk();

        Storage::disk('public')->assertMissing("openai/{$user->id}/orphan.png");
    }

    public function test_destroy_returns_404_for_a_filename_that_does_not_exist(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($user)
            ->delete(route('posts.image-archive.destroy', [$user, 'nope.png']))
            ->assertNotFound();
    }

    public function test_manager_cannot_delete_an_image_of_an_account_they_do_not_own(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);

        Storage::disk('public')->put("openai/{$childOfB->id}/img.png", 'fake');

        $this->actingAs($managerA)
            ->delete(route('posts.image-archive.destroy', [$childOfB, 'img.png']))
            ->assertForbidden();

        Storage::disk('public')->assertExists("openai/{$childOfB->id}/img.png");
    }
}
