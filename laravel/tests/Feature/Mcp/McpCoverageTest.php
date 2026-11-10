<?php

use App\Mcp\Servers\FaPer3Server;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Ogni rotta dell'app deve avere una decisione MCP: il tool che la copre,
 * "da fare: …" (pianificato) oppure "esclusa: motivo". Una rotta nuova
 * senza voce qui fa fallire il test: è il promemoria per esporre (o escludere
 * consapevolmente) ogni funzionalità futura anche nel server MCP.
 */
const MCP_COVERAGE = [
    // Dashboard, ricerca, calendario
    'home' => 'get_dashboard_metrics, list_posts',
    'dashboard' => 'get_dashboard_metrics, list_posts',
    'search' => 'list_posts, list_accounts',
    'schedule' => 'get_calendar',
    'scope.update' => 'esclusa: lo scope di sessione è l\'argomento account_id dei tool',

    // Post
    'posts' => 'list_posts',
    'posts.show' => 'get_post',
    'posts.create' => 'list_channel_options',
    'posts.edit' => 'get_post, list_channel_options',
    'posts.store' => 'create_post, publish_post_now',
    'posts.update' => 'update_post, publish_post_now',
    'posts.retry' => 'retry_post',
    'posts.duplicate' => 'duplicate_post',
    'posts.channel-content.update' => 'update_published_post',
    'posts.destroy' => 'delete_post',
    'posts.generate-text' => 'generate_post_text',
    'posts.link-targets' => 'list_posts, get_post',
    'posts.image-archive' => 'list_image_archive',
    'posts.image-generate' => 'generate_image',
    'posts.image-status' => 'get_image_job',
    'posts.image-archive.destroy' => 'esclusa: pulizia archivio solo da interfaccia',
    'posts.wordpress-categories' => 'list_channel_options, refresh_channel_options',
    'posts.newsletter-lists' => 'list_channel_options, refresh_channel_options',
    'posts.newsletter-tags' => 'list_channel_options',

    // Account: sola lettura via MCP, chiavi mai (decisione 2026-11-03)
    'account' => 'list_accounts',
    'account.edit' => 'list_accounts',
    'account.create' => 'esclusa: gestione account solo da interfaccia',
    'account.store' => 'esclusa: gestione account solo da interfaccia',
    'account.update' => 'esclusa: gestione account e chiavi solo da interfaccia',
    'account.destroy' => 'esclusa: gestione account solo da interfaccia',
    'linkedin.redirect' => 'esclusa: OAuth LinkedIn richiede il browser',
    'linkedin.callback' => 'esclusa: OAuth LinkedIn richiede il browser',
    'linkedin.page.update' => 'esclusa: configurazione account',
    'wordpress.categories' => 'refresh_channel_options',
    'newsletter.lists' => 'refresh_channel_options',
    'contacts.api-key.regenerate' => 'esclusa: chiavi solo da interfaccia',

    // Impostazioni personali
    'settings' => 'whoami',
    'settings.update' => 'esclusa: credenziali di accesso solo da interfaccia',
    'settings.mcp-tokens.store' => 'esclusa: un token non crea altri token',
    'settings.mcp-tokens.destroy' => 'esclusa: un token non gestisce i token',

    // Contatti
    'contacts' => 'list_contacts',
    'contacts.create' => 'list_channel_options',
    'contacts.edit' => 'list_contacts',
    'contacts.store' => 'create_contact',
    'contacts.import' => 'esclusa: upload file CSV solo da interfaccia',
    'contacts.update' => 'update_contact',
    'contacts.destroy' => 'delete_contact',
    'POST api/contacts' => 'esclusa: API server-to-server con la propria chiave',

    // Notifiche push
    'notifications' => 'list_push_notifications',
    'notifications.create' => 'list_push_notifications',
    'notifications.store' => 'create_push_notification',
    'notifications.edit' => 'list_push_notifications',
    'notifications.update' => 'update_push_notification',
    'notifications.destroy' => 'delete_push_notification',
    'push.subscribe' => 'esclusa: iscrizione del browser alle notifiche',
    'push.unsubscribe' => 'esclusa: iscrizione del browser alle notifiche',
    'push.unread' => 'esclusa: campanella del browser',
    'push.mark-read' => 'esclusa: campanella del browser',

    // Pubbliche (destinatari newsletter, non utenti)
    'newsletter.unsubscribe' => 'esclusa: pagina pubblica per i destinatari',
    'newsletter.unsubscribe.confirm' => 'esclusa: pagina pubblica per i destinatari',
    'newsletter.pixel' => 'esclusa: pixel di tracciamento',
];

function appRouteKeys(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => Str::startsWith(ltrim($r->getActionName(), '\\'), 'App\\Http\\Controllers\\'))
        ->map(fn ($r) => $r->getName() ?? collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->implode('|').' '.$r->uri())
        ->unique()
        ->values()
        ->all();
}

it('has an MCP decision for every app route', function () {
    $missing = array_diff(appRouteKeys(), array_keys(MCP_COVERAGE));

    expect($missing)->toBe([], 'Rotte senza decisione MCP (aggiungile in McpCoverageTest): '.implode(', ', $missing));
});

it('does not list routes that no longer exist', function () {
    $stale = array_diff(array_keys(MCP_COVERAGE), appRouteKeys());

    expect(array_values($stale))->toBe([]);
});

it('only maps routes to tools that exist', function () {
    $tools = collect(FaPer3Server::discover('Tools'))->map(fn ($class) => app($class)->name())->all();

    $referenced = collect(MCP_COVERAGE)
        ->reject(fn ($v) => Str::startsWith($v, ['esclusa:', 'da fare:']))
        ->flatMap(fn ($v) => array_map('trim', explode(',', $v)))
        ->unique()
        ->values()
        ->all();

    expect(array_values(array_diff($referenced, $tools)))->toBe([]);
});
