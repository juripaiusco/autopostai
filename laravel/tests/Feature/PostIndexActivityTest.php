<?php

use App\Mcp\Servers\FaPer3Server;
use App\Mcp\Tools\ListPosts;
use App\Models\Contact;
use App\Models\EmailSend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function activityPost(User $account, array $channels, array $overrides = []): Post
{
    return Post::factory()->create(array_merge([
        'user_id' => $account->id,
        'created_by_user_id' => $account->id,
        'channels' => $channels,
        'published' => '1',
        'published_at' => now()->subHour(),
    ], $overrides));
}

function smtpChannel(array $extra = []): array
{
    return ['newsletter' => array_merge([
        'on' => true,
        'provider' => 'smtp_custom',
        'id' => 'smtp-1',
        'stats' => ['queued' => 0, 'sent' => 40, 'delivered' => 0, 'opened' => 8, 'clicked' => 2, 'bounced' => 1],
        'recipients' => 100,
    ], $extra)];
}

test('smtp newsletter in progress exposes sent/total/opened and the sending flag', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, smtpChannel());

    $this->actingAs($account)->get(route('posts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.newsletter.sent', 51)
            ->where('posts.data.0.newsletter.total', 100)
            ->where('posts.data.0.newsletter.opened', 10)
            ->where('posts.data.0.newsletter.bounced', 1)
            ->where('posts.data.0.newsletter.sending', true)
            ->where('posts.data.0.newsletter.campaign', false)
        );
});

test('smtp newsletter completed is no longer sending', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, smtpChannel(['completed_at' => now()->toDateTimeString()]));

    $this->actingAs($account)->get(route('posts'))
        ->assertInertia(fn (Assert $page) => $page->where('posts.data.0.newsletter.sending', false));
});

test('smtp newsletter sent before recipients existed has a null total', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $channels = smtpChannel();
    unset($channels['newsletter']['recipients']);
    activityPost($account, $channels);

    $this->actingAs($account)->get(route('posts'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.newsletter.total', null)
            ->where('posts.data.0.newsletter.sent', 51)
        );
});

test('mailchimp newsletter only reports the campaign, without counts', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, ['newsletter' => ['on' => true, 'provider' => 'mailchimp', 'id' => 'abc123']]);

    $this->actingAs($account)->get(route('posts'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.newsletter.campaign', true)
            ->where('posts.data.0.newsletter.sent', null)
            ->where('posts.data.0.newsletter.sending', false)
        );
});

test('newsletter is null when the channel is off or not yet sent', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, ['facebook' => ['on' => true]], ['title' => 'Social']);
    activityPost($account, ['newsletter' => ['on' => true, 'provider' => 'smtp_custom']], [
        'title' => 'Programmato', 'published' => '0', 'published_at' => now()->addDay(),
    ]);

    $this->actingAs($account)->get(route('posts', ['sort' => 'title', 'dir' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.title', 'Programmato')
            ->where('posts.data.0.newsletter', null)
            ->where('posts.data.1.newsletter', null)
        );
});

test('sorting by comments is no longer accepted and falls back to the date', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, ['facebook' => ['on' => true]], ['title' => 'Vecchio', 'published_at' => now()->subDays(2)]);
    activityPost($account, ['facebook' => ['on' => true]], ['title' => 'Recente', 'published_at' => now()->subHour()]);

    $this->actingAs($account)->get(route('posts', ['sort' => 'comments', 'dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.title', 'Vecchio')
            ->where('posts.data.1.title', 'Recente')
        );
});

test('the open pixel keeps the recipients total written by the worker', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = activityPost($account, smtpChannel());
    $contact = Contact::factory()->create(['user_id' => $account->id]);
    $send = EmailSend::create(['contact_id' => $contact->id, 'post_id' => $post->id, 'status' => 'sent', 'sent_at' => now()]);

    $this->get(route('newsletter.pixel', ['emailSend' => $send->id]))->assertOk();

    expect($post->fresh()->channels['newsletter']['recipients'])->toBe(100);
});

test('list_posts exposes the newsletter progress', function () {
    $account = User::factory()->create(['parent_id' => null]);
    activityPost($account, smtpChannel());

    FaPer3Server::actingAs(withMcp($account))->tool(ListPosts::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('posts.0.newsletter.sent', 51)
            ->where('posts.0.newsletter.total', 100)
            ->etc()
        );
});
