<?php

namespace Tests\Feature;

use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_images_from_both_provider_folders_and_resolves_prompt_when_available(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("dall-e/{$user->id}/with-job.png", 'fake');
        Storage::disk('public')->put("dall-e/{$user->id}/orphan.png", 'fake');
        Storage::disk('public')->put("stable-diffusion/{$user->id}/sd-orphan.jpg", 'fake');
        Storage::disk('public')->put("dall-e/{$user->id}/.listing", 'not an image');

        ImageJob::factory()->create([
            'user_id' => $user->id,
            'image_url' => 'with-job.png',
            'prompt' => 'Una pizza margherita',
            'model' => 'dall-e-3',
        ]);

        $response = $this->actingAs($user)->get(route('posts.image-archive', $user));

        $response->assertOk();
        $images = collect($response->json('images'));

        $this->assertCount(3, $images);
        $this->assertFalse($images->contains('filename', '.listing'));

        $withJob = $images->firstWhere('filename', 'with-job.png');
        $this->assertSame('Una pizza margherita', $withJob['prompt']);

        $orphan = $images->firstWhere('filename', 'orphan.png');
        $this->assertNull($orphan['prompt']);

        $sdOrphan = $images->firstWhere('filename', 'sd-orphan.jpg');
        $this->assertNull($sdOrphan['prompt']);
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

    public function test_store_saves_the_file_and_creates_an_image_job_with_the_prompt(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $response = $this->actingAs($user)->post(route('posts.image-generate', $user), [
            'prompt' => 'Un gatto astronauta',
            'image' => UploadedFile::fake()->image('generata.png'),
        ]);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame('Un gatto astronauta', $json['prompt']);
        $this->assertSame('dall-e-3', $json['model']);

        Storage::disk('public')->assertExists("dall-e/{$user->id}/{$json['filename']}");

        $this->assertDatabaseHas('image_jobs', [
            'user_id' => $user->id,
            'image_url' => $json['filename'],
            'prompt' => 'Un gatto astronauta',
        ]);
    }

    public function test_destroy_deletes_the_file_and_its_image_job(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("dall-e/{$user->id}/with-job.png", 'fake');
        ImageJob::factory()->create(['user_id' => $user->id, 'image_url' => 'with-job.png']);

        $this->actingAs($user)
            ->delete(route('posts.image-archive.destroy', [$user, 'with-job.png']))
            ->assertOk();

        Storage::disk('public')->assertMissing("dall-e/{$user->id}/with-job.png");
        $this->assertDatabaseMissing('image_jobs', ['user_id' => $user->id, 'image_url' => 'with-job.png']);
    }

    public function test_destroy_deletes_an_orphan_file_with_no_image_job(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        Storage::disk('public')->put("stable-diffusion/{$user->id}/orphan.jpg", 'fake');

        $this->actingAs($user)
            ->delete(route('posts.image-archive.destroy', [$user, 'orphan.jpg']))
            ->assertOk();

        Storage::disk('public')->assertMissing("stable-diffusion/{$user->id}/orphan.jpg");
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

        Storage::disk('public')->put("dall-e/{$childOfB->id}/img.png", 'fake');

        $this->actingAs($managerA)
            ->delete(route('posts.image-archive.destroy', [$childOfB, 'img.png']))
            ->assertForbidden();

        Storage::disk('public')->assertExists("dall-e/{$childOfB->id}/img.png");
    }
}
