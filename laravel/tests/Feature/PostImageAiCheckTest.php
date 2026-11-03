<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "L'AI guarda l'immagine" (img_ai_check_on) e' una scelta esplicita del form,
 * come il check di v1: non dipende piu' da dove arriva l'immagine (prima
 * un'immagine generata con AI la accendeva da sola, una caricata la spegneva).
 */
class PostImageAiCheckTest extends TestCase
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

    private function makeDraft(User $user, string $imgAiCheckOn): Post
    {
        return Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '0',
            'published_at' => null,
            'channels' => ['facebook' => ['on' => true]],
            'img_ai_check_on' => $imgAiCheckOn,
        ]);
    }

    public function test_store_saves_the_flag_as_chosen_in_the_form(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Con check',
            'channels' => ['facebook' => []],
            'images' => [UploadedFile::fake()->image('foto.jpg')],
            'img_ai_check_on' => '1',
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Senza check',
            'channels' => ['facebook' => []],
            'images' => [UploadedFile::fake()->image('foto.jpg')],
            'img_ai_check_on' => '0',
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $this->assertSame('1', Post::where('title', 'Con check')->value('img_ai_check_on'));
        $this->assertSame('0', Post::where('title', 'Senza check')->value('img_ai_check_on'));
    }

    public function test_update_can_turn_the_flag_on_and_off_without_new_images(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, '0');

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => []],
            'img_ai_check_on' => '1',
        ])->assertRedirect(route('posts'));
        $this->assertSame('1', $post->fresh()->img_ai_check_on);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => []],
            'img_ai_check_on' => '0',
        ])->assertRedirect(route('posts'));
        $this->assertSame('0', $post->fresh()->img_ai_check_on);
    }

    public function test_update_without_the_field_keeps_the_saved_value(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, '1');

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => []],
        ])->assertRedirect(route('posts'));

        $this->assertSame('1', $post->fresh()->img_ai_check_on);
    }

    public function test_edit_form_receives_the_flag_as_boolean(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, '1');

        $this->actingAs($user)->get(route('posts.edit', $post))
            ->assertInertia(fn ($page) => $page
                ->component('Posts/Form')
                ->where('post.img_ai_check_on', true)
            );
    }
}
