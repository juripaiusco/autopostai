<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true]],
        ]);
    }

    public function test_storing_a_post_with_multiple_images_saves_them_all_in_a_per_post_folder(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post con 3 immagini',
            'channels' => ['facebook' => []],
            'images' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
                UploadedFile::fake()->image('foto3.jpg'),
            ],
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::where('title', 'Post con 3 immagini')->firstOrFail();

        $this->assertCount(3, $post->img);
        foreach ($post->img as $filename) {
            Storage::disk('public')->assertExists("posts/{$post->id}/{$filename}");
        }
    }

    public function test_updating_a_post_keeps_existing_images_removes_dropped_ones_and_adds_new_ones(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '0',
            'published_at' => null,
            'channels' => ['facebook' => ['on' => true]],
        ]);

        // Immagini "esistenti" simulate direttamente sul disco fake.
        Storage::disk('public')->put("posts/{$post->id}/keep-me.jpg", 'fake');
        Storage::disk('public')->put("posts/{$post->id}/remove-me.jpg", 'fake');
        $post->update(['img' => ['keep-me.jpg', 'remove-me.jpg']]);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => []],
            'keep_images' => json_encode(['keep-me.jpg']),
            'images' => [UploadedFile::fake()->image('nuova.jpg')],
        ])->assertRedirect(route('posts'));

        $post->refresh();

        $this->assertContains('keep-me.jpg', $post->img);
        $this->assertNotContains('remove-me.jpg', $post->img);
        $this->assertCount(2, $post->img);

        Storage::disk('public')->assertExists("posts/{$post->id}/keep-me.jpg");
        Storage::disk('public')->assertMissing("posts/{$post->id}/remove-me.jpg");
    }

    public function test_show_page_exposes_resolved_urls_for_every_stored_image(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '1',
            'channels' => ['facebook' => ['on' => true]],
            'img' => ['a.jpg', 'b.jpg'],
        ]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));

        $response->assertInertia(fn ($page) => $page
            ->component('Posts/Show')
            ->has('post.images', 2)
            ->where('post.images.0.filename', 'a.jpg')
        );
    }

    public function test_single_image_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['facebook' => []],
            'images' => [UploadedFile::fake()->image('big.jpg')->size(10241)],
            'action' => 'save',
        ])->assertSessionHasErrors('images.0');
    }
}
