// _pwa-generator.cjs
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const icon     = 'public/pwa-faper3-logo.png';
const outBase  = 'public/assets/splash';
const bladeOut = 'resources/views/includes/pwa-splash.blade.php';

const combos = [
    { theme: 'dark',  bg: 'rgb(31 41 55)', mode: 'portrait' },
    { theme: 'dark',  bg: 'rgb(31 41 55)', mode: 'landscape' },
    /*{ theme: 'light', bg: 'rgb(255 255 255)', mode: 'portrait',  padding: '35%' },
    { theme: 'light', bg: 'rgb(255 255 255)', mode: 'landscape', padding: '15%' },*/
];

// Regex per prendere SOLO i <link> degli splash iOS
const linkRegex = /<link[^>]+rel=["']apple-touch-startup-image["'][^>]*>/gi;

const allLinks = [];
const seen = new Set();

for (const { theme, bg, mode, padding } of combos) {
    const outDir = path.join(outBase, theme);
    const cmd = `pwa-asset-generator ${icon} ${outDir} ` +
        `--splash-only --background "${bg}" --padding "${padding}" ` +
        `--${mode}-only --single-quotes`;

    // Catturo l'output (niente stdio: 'inherit', così non finisce nel file)
    const stdout = execSync(cmd, { encoding: 'utf8' });

    // Estrai SOLO i <link>
    const links = stdout.match(linkRegex) || [];
    for (let link of links) {
        const hrefMatch  = link.match(/href=["']([^"']+)["']/i);
        const mediaMatch = link.match(/media=["']([^"']+)["']/i);
        if (!hrefMatch || !mediaMatch) continue;

        const origMedia = mediaMatch[1];
        const fileName  = path.basename(hrefMatch[1]); // es: apple-splash-1170-2532.jpg

        // href in formato Blade
        const newHref  = `href="{{ URL::asset('assets/splash/${theme}/${fileName}') }}"`;
        // aggiungo il prefers-color-scheme
        const newMedia = `media="(prefers-color-scheme: ${theme}) and ${origMedia}"`;

        let cleaned = link;
        cleaned = cleaned.replace(/href=["'][^"']+["']/, newHref);

        // Non pulisco il tema perché iOS prende lo splash screen in base al tema e lo mantiene
        // sempre senza mai cambiando, anche se il tema color cambia
        // cleaned = cleaned.replace(/media=["'][^"']+["']/, newMedia);

        // chiave per evitare duplicati
        const key = `${theme}|${mode}|${fileName}|${origMedia}`;
        if (!seen.has(key)) {
            seen.add(key);
            allLinks.push(cleaned);
        }
    }
}

// Scrivo SOLO i <link> nel Blade
fs.writeFileSync(bladeOut, allLinks.join('\n') + '\n', 'utf8');
console.log(`✅ Aggiornato ${bladeOut} con ${allLinks.length} tag <link>.`);
