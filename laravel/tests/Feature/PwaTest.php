<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asset PWA generati da `npm run pwa:generate` (scripts/pwa-generate.mjs):
 * se mancano o il manifest punta a file inesistenti, l'app non è installabile.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_links_manifest_and_generated_head(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest?v='.config('app.version').'">', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('rel="apple-touch-startup-image"', false);
    }

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['name', 'short_name', 'start_url', 'display', 'background_color', 'theme_color'] as $key) {
            $this->assertNotEmpty($manifest[$key] ?? null, "manifest senza {$key}");
        }
        $this->assertSame('standalone', $manifest['display']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_service_worker_offline_assets_exist(): void
    {
        $sw = file_get_contents(public_path('service-worker.js'));

        preg_match('/OFFLINE_ASSETS = \[([^\]]+)\]/', $sw, $match);
        $this->assertNotEmpty($match, 'OFFLINE_ASSETS non trovato nel service worker');

        // cache.addAll() fallisce intera se anche un solo file manca: niente pagina offline.
        $this->assertStringContainsString("OFFLINE_URL = '/offline.html'", $sw);
        $this->assertFileExists(public_path('offline.html'));
        preg_match_all("/'([^']+)'/", $match[1], $paths);
        foreach ($paths[1] as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }
    }

    public function test_generated_head_points_to_existing_files(): void
    {
        $head = file_get_contents(resource_path('views/partials/pwa-head.blade.php'));

        preg_match_all('/href="([^"]+)"/', $head, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            $this->assertFileExists(public_path(ltrim($href, '/')));
        }
    }
}
