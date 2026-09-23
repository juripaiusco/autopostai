<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Contact;
use App\Models\EmailSend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dashboard fase 1: metriche vere da dati interni (niente visualizzazioni).
 * Periodo 90 giorni, variazione sui 90 precedenti.
 */
class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function published(User $owner, array $channels, int $daysAgo = 5): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => collect($channels)->mapWithKeys(fn ($id, $ch) => [$ch => ['on' => true, 'id' => $id]])->all(),
            'published' => '1',
            'published_at' => now()->subDays($daysAgo),
        ]);
    }

    private function metrics(User $viewer): array
    {
        $metrics = null;
        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertInertia(function (Assert $page) use (&$metrics) {
                $metrics = $page->toArray()['props']['metrics'];
            });

        return $metrics;
    }

    public function test_counts_published_posts_outputs_comments_and_replies(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $post = $this->published($user, ['facebook' => '1_1', 'linkedin' => 'urn:li:share:1']);
        // Bozza e canale acceso ma mai uscito: non contano.
        Post::factory()->create(['user_id' => $user->id, 'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]], 'published' => '0', 'published_at' => null]);

        Comment::factory()->count(3)->create(['post_id' => $post->id, 'channel' => 'facebook',
            'reply_id' => null, 'reply' => null, 'reply_created_time' => null]);
        Comment::factory()->create(['post_id' => $post->id, 'channel' => 'facebook']); // con risposta AI

        $m = $this->metrics($user);

        $this->assertSame(1, $m['stats']['posts']['value']);
        $this->assertSame(2, $m['stats']['outputs']['value']);
        $this->assertSame(4, $m['stats']['comments']['value']);
        $this->assertSame(1, $m['stats']['replies']['value']);

        $facebook = collect($m['channels'])->firstWhere('id', 'facebook');
        $this->assertSame(['id' => 'facebook', 'posts' => 1, 'comments' => 4, 'replies' => 1, 'opens' => null], $facebook);
        $this->assertSame(1, collect($m['channels'])->firstWhere('id', 'linkedin')['posts']);
    }

    public function test_trend_compares_with_previous_period_and_is_null_without_history(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $this->published($user, ['facebook' => '1_1'], 5);
        $this->published($user, ['facebook' => '1_2'], 10);
        $this->published($user, ['facebook' => '1_3'], 120); // periodo precedente

        $m = $this->metrics($user);

        $this->assertSame(2, $m['stats']['posts']['value']);
        $this->assertSame(100, $m['stats']['posts']['trend']);
        $this->assertNull($m['stats']['comments']['trend']); // nessun commento prima: niente %
        $this->assertCount(7, $m['stats']['posts']['spark']);
    }

    public function test_monthly_series_covers_twelve_months_ending_now(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $this->published($user, ['facebook' => '1_1'], 0);

        $monthly = $this->metrics($user)['monthly'];

        $this->assertCount(12, $monthly);
        $this->assertSame(1, $monthly[11]['posts']);
    }

    public function test_newsletter_opens_are_counted_for_the_newsletter_channel(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->published($user, ['newsletter' => 'smtp-1']);
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        EmailSend::factory()->create(['post_id' => $post->id, 'contact_id' => $contact->id,
            'status' => 'opened', 'opened_at' => now()->subDay()]);

        $newsletter = collect($this->metrics($user)['channels'])->firstWhere('id', 'newsletter');

        $this->assertSame(1, $newsletter['opens']);
    }

    public function test_metrics_respect_visibility(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);
        $this->published($stranger, ['facebook' => '9_9']);

        $this->assertSame(0, $this->metrics($user)['stats']['posts']['value']);
        $this->assertSame(1, $this->metrics($admin)['stats']['posts']['value']);
    }
}
