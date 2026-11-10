#!/usr/bin/env node
/**
 * Genera gli asset PWA: icone, splash screen iOS, manifest e meta tag per il <head>.
 *
 *     npm run pwa:generate
 *
 * Gira sull'HOST (serve Chrome, usato da pwa-asset-generator via puppeteer).
 * Ogni lancio svuota e rigenera tutto: niente pulizia a mano.
 *
 * Output (versionato in git: il deploy prod parte dal repo pulito):
 *   public/pwa/icons/                         favicon, apple-touch-icon, icone del manifest
 *   public/pwa/splash/                        splash iOS (apple-touch-startup-image)
 *   public/manifest.webmanifest               web app manifest
 *   resources/views/partials/pwa-head.blade.php   <link> per favicon/icona/splash
 *
 * Doc: dev/docs/pwa.md
 */
import { generateImages } from 'pwa-asset-generator';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');

// --- Impostazioni -----------------------------------------------------------
const APP_NAME = 'FaPer3';
const ICON_SOURCE = 'public/images/faper3-logo.png'; // quadrata, con trasparenza
const ICON_BACKGROUND = '#ffffff';
const ICON_PADDING = '20%'; // margine per la zona sicura delle icone "maskable"
const SPLASH_SOURCE = 'resources/pwa/splash-source.png'; // verticale, con trasparenza
const SPLASH_BACKGROUND = '#1f2937'; // scuro come v1; è anche il background_color del manifest
const SPLASH_PADDING = '0'; // l'immagine ha già i suoi margini
const THEME_COLOR = '#ffffff'; // barra di stato: top bar bianca dell'app
// ---------------------------------------------------------------------------

const ICONS_DIR = 'public/pwa/icons';
const SPLASH_DIR = 'public/pwa/splash';
const MANIFEST_FILE = 'public/manifest.webmanifest';
const HEAD_PARTIAL = 'resources/views/partials/pwa-head.blade.php';

const abs = (path) => join(ROOT, path);

async function cleanDir(path) {
    await rm(abs(path), { recursive: true, force: true });
    await mkdir(abs(path), { recursive: true });
}

await cleanDir(ICONS_DIR);
await cleanDir(SPLASH_DIR);

console.log('Icone…');
const icons = await generateImages(abs(ICON_SOURCE), abs(ICONS_DIR), {
    iconOnly: true,
    favicon: true,
    opaque: true,
    background: ICON_BACKGROUND,
    padding: ICON_PADDING,
    type: 'png',
    maskable: false, // purpose any/maskable li scriviamo noi sotto, separati
    pathOverride: '/pwa/icons',
    log: false,
});

console.log('Splash iOS…');
const splash = await generateImages(abs(SPLASH_SOURCE), abs(SPLASH_DIR), {
    splashOnly: true,
    opaque: true,
    background: SPLASH_BACKGROUND,
    padding: SPLASH_PADDING,
    type: 'jpg',
    quality: 80,
    pathOverride: '/pwa/splash',
    log: false,
});

// Stessa immagine dichiarata due volte: "any" e "maskable" separati
// (Chrome sconsiglia "any maskable" insieme).
const manifestIcons = icons.manifestJsonContent.flatMap(({ src, sizes, type }) => [
    { src, sizes, type, purpose: 'any' },
    { src, sizes, type, purpose: 'maskable' },
]);

const manifest = {
    name: APP_NAME,
    short_name: APP_NAME,
    lang: 'it',
    start_url: '/',
    scope: '/',
    display: 'standalone',
    background_color: SPLASH_BACKGROUND,
    theme_color: THEME_COLOR,
    icons: manifestIcons,
};
await writeFile(abs(MANIFEST_FILE), JSON.stringify(manifest, null, 4) + '\n');

const meta = (html) => (html ?? '').trim();
const partial = [
    '{{-- GENERATO da `npm run pwa:generate` (scripts/pwa-generate.mjs): non modificare a mano. --}}',
    meta(icons.htmlMeta.favicon),
    meta(icons.htmlMeta.appleTouchIcon),
    meta(splash.htmlMeta.appleLaunchImage),
].filter(Boolean).join('\n') + '\n';
await mkdir(dirname(abs(HEAD_PARTIAL)), { recursive: true });
await writeFile(abs(HEAD_PARTIAL), partial);

const splashCount = splash.savedImages.length;
console.log(`Fatto: ${icons.savedImages.length} icone, ${splashCount} splash, ${MANIFEST_FILE}, ${HEAD_PARTIAL}`);
