// Testo del post per famiglia di canale (vedi docs/editor-anteprima.md).
// Articolo (WordPress, Newsletter): HTML dell'editor ricco, titolo nel primo
// <h1>. Social (Facebook, Instagram, LinkedIn): testo semplice.

export const ARTICLE_CHANNELS = ['wordpress', 'newsletter'];

export function hasArticleChannel(channelIds) {
    return channelIds.some((id) => ARTICLE_CHANNELS.includes(id));
}

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// "<h1>Titolo</h1><p>…</p>" → { title: 'Titolo', body: '<p>…</p>' }.
export function splitArticle(html) {
    const source = String(html ?? '').trim();
    const match = source.match(/<h1\b[^>]*>([\s\S]*?)<\/h1>/i);
    if (!match) return { title: '', body: source };
    const doc = new DOMParser().parseFromString(match[1], 'text/html');
    return {
        title: (doc.body.textContent ?? '').trim(),
        body: (source.slice(0, match.index) + source.slice(match.index + match[0].length)).trim(),
    };
}

export function joinArticle(title, body) {
    const t = String(title ?? '').trim();
    return (t ? `<h1>${escapeHtml(t)}</h1>` : '') + String(body ?? '');
}

// Testo semplice → paragrafi HTML (riga vuota = nuovo paragrafo, a capo = <br>).
export function textToHtml(text) {
    return String(text ?? '')
        .split(/\n\s*\n/)
        .map((block) => block.trim())
        .filter(Boolean)
        .map((block) => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

// HTML → testo semplice, blocchi separati da una riga vuota, link come
// "testo (url)". Stesse regole del worker (publisher/domain/channel_text.py):
// qui serve per l'anteprima e il contatore dei social. Gli shortcode
// (link a un tuo post, CTA) li risolve il worker all'uscita: qui restano
// leggibili come "(link al post)".
export function htmlToText(html) {
    const doc = new DOMParser().parseFromString(String(html ?? ''), 'text/html');
    doc.querySelectorAll('a[href]').forEach((a) => {
        const raw = a.getAttribute('href');
        const url = raw.startsWith('[') ? 'link al post' : raw;
        const label = (a.textContent ?? '').trim();
        a.replaceWith(!label || label === raw ? url : `${label} (${url})`);
    });
    doc.querySelectorAll('p').forEach((p) => {
        const cta = (p.textContent ?? '').trim().match(/^\[cta\b[\s\S]*?text="([^"]*)"[\s\S]*\]$/i);
        if (cta) p.textContent = `${cta[1]}: (link)`;
    });
    doc.querySelectorAll('br').forEach((br) => br.replaceWith('\n'));
    doc.querySelectorAll('p, h1, h2, h3, li, blockquote').forEach((el) => el.append('\n\n'));

    return (doc.body.textContent ?? '')
        .replace(/[ \t]+/g, ' ')
        .replace(/\n\s*\n(\s*\n)+/g, '\n\n')
        .split('\n')
        .map((line) => line.trim())
        .join('\n')
        .trim();
}

// Testo e formato coerenti con i canali scelti, al salvataggio: l'utente può
// cambiare i canali dopo aver scritto. Social → articolo: il testo diventa
// paragrafi; articolo → solo social: l'HTML diventa testo.
export function normalizeContent(content, format, channelIds) {
    const text = String(content ?? '');
    if (text.trim() === '') return { content: text, format: 'markdown' };

    const article = hasArticleChannel(channelIds);
    if (article && format !== 'html') return { content: textToHtml(text), format: 'html' };
    if (!article && format === 'html') return { content: htmlToText(text), format: 'text' };
    if (!article) return { content: text, format: 'text' };

    return { content: text, format };
}
