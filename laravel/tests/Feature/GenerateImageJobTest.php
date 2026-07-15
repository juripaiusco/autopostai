<?php

namespace Tests\Feature;

use App\Jobs\GenerateImageJob;
use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateImageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_stores_the_image_and_marks_the_job_completed_on_success(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $job = ImageJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'image_url' => null,
            'model' => 'gpt-image-1',
            'prompt' => 'Un gatto astronauta',
        ]);

        Http::fake([
            '*/generate-image' => Http::response([
                'image_base64' => base64_encode('fake-image-bytes'),
                'mime_type' => 'image/png',
            ]),
        ]);

        (new GenerateImageJob($job->id))->handle();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertNotNull($job->image_url);
        Storage::disk('public')->assertExists("openai/{$user->id}/{$job->image_url}");
        $this->assertSame('fake-image-bytes', Storage::disk('public')->get("openai/{$user->id}/{$job->image_url}"));
    }

    public function test_handle_marks_the_job_failed_when_the_python_service_errors(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $job = ImageJob::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'image_url' => null,
            'model' => 'gpt-image-1',
        ]);

        Http::fake([
            '*/generate-image' => Http::response(['detail' => 'chiave non valida'], 422),
        ]);

        (new GenerateImageJob($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertNull($job->image_url);
    }
}
