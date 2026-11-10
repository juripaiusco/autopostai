// Shortcode degli editor, senza che l'utente debba scriverli:
// - `[canale url id=N]`  → indirizzo del post N su quel canale (link a un tuo post)
// - `[cta …]`            → pulsante call-to-action della newsletter
// Li risolve il worker all'uscita (publisher/integrations/shortcodes.py); qui
// vengono scritti, riconosciuti e mostrati in modo leggibile.
import { Node, mergeAttributes } from '@tiptap/vue-3';

export const CHANNEL_LABELS = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    wordpress: 'WordPress',
    newsletter: 'Newsletter',
};

const POST_LINK_RE = /^\[(\w+) url id=(\d+)\]$/i;

export function postLinkShortcode(channel, id) {
    return `[${channel} url id=${id}]`;
}

// "[wordpress url id=12]" → { channel: 'wordpress', id: 12 } (null se non lo è).
export function parsePostLink(value) {
    const m = String(value ?? '').trim().match(POST_LINK_RE);
    if (!m || !CHANNEL_LABELS[m[1].toLowerCase()]) return null;
    return { channel: m[1].toLowerCase(), id: Number(m[2]) };
}

export function describePostLink(value, title = null) {
    const link = parsePostLink(value);
    if (!link) return null;
    return `${title || `Post #${link.id}`} · ${CHANNEL_LABELS[link.channel]}`;
}

// Virgolette e parentesi quadre chiuderebbero lo shortcode: nel testo
// diventano tipografiche / tonde.
function quoteSafe(text) {
    return String(text ?? '').replace(/"/g, '”').replace(/\[/g, '(').replace(/\]/g, ')').trim();
}

export function ctaShortcode({ text, url, channel, postId }) {
    const label = quoteSafe(text);
    return channel && postId
        ? `[cta url ${channel} id=${postId} text="${label}"]`
        : `[cta url="${String(url ?? '').replace(/"/g, '%22')}" text="${label}"]`;
}

// Stesse regole di parse_cta (Python): coppie key="valore" e parole "flag".
export function parseCta(value) {
    const m = String(value ?? '').trim().match(/^\[cta\b([\s\S]*)\]$/i);
    if (!m) return null;
    const raw = m[1];
    const attrs = {};
    const attrRe = /(\w+)=(?:"([^"]*)"|'([^']*)'|(\S+))/g;
    let a;
    while ((a = attrRe.exec(raw)) !== null) attrs[a[1].toLowerCase()] = a[2] ?? a[3] ?? a[4];
    const flags = raw.replace(attrRe, ' ').toLowerCase().match(/[a-z]+/g) ?? [];
    const channel = flags.find((f) => CHANNEL_LABELS[f]) ?? null;

    return {
        text: attrs.text ?? '',
        url: attrs.url ?? '',
        channel: attrs.url === undefined ? channel : null,
        postId: attrs.url === undefined && attrs.id ? Number(attrs.id) : null,
    };
}

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function removeButton(editor, getPos, node) {
    const btn = el('button', 'rte-chip-x', '×');
    btn.type = 'button';
    btn.title = 'Rimuovi';
    btn.setAttribute('aria-label', 'Rimuovi');
    btn.addEventListener('mousedown', (e) => e.preventDefault());
    btn.addEventListener('click', () => {
        const pos = getPos();
        if (typeof pos === 'number' && editor.isEditable) {
            editor.chain().focus().deleteRange({ from: pos, to: pos + node.nodeSize }).run();
        }
    });
    return btn;
}

/**
 * Pulsante CTA (editor ricco, solo con la newsletter accesa). Salvato come
 * paragrafo con lo shortcode `[cta …]`: il sanitizer lascia il testo, il
 * worker lo espande col template CTA dell'account (o in un link su WordPress).
 */
export const CtaButton = Node.create({
    name: 'ctaButton',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,

    addAttributes() {
        return {
            text: { default: '' },
            url: { default: '' },
            channel: { default: null },
            postId: { default: null },
            title: { default: null, rendered: false }, // solo a video
        };
    },

    parseHTML() {
        return [{
            tag: 'p',
            priority: 60,
            getAttrs: (dom) => parseCta(dom.textContent) ?? false,
        }];
    },

    renderHTML({ node }) {
        return ['p', {}, ctaShortcode(node.attrs)];
    },

    renderText({ node }) {
        return ctaShortcode(node.attrs);
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const dom = el('div', 'rte-cta');
            dom.contentEditable = 'false';
            dom.append(el('span', 'rte-cta-btn', node.attrs.text || 'Pulsante'));
            const target = node.attrs.channel && node.attrs.postId
                ? `→ ${node.attrs.title || `Post #${node.attrs.postId}`} · ${CHANNEL_LABELS[node.attrs.channel] ?? node.attrs.channel}`
                : `→ ${node.attrs.url}`;
            dom.append(el('span', 'rte-cta-target', target));
            dom.append(removeButton(editor, getPos, node));
            return { dom };
        };
    },
});

/**
 * Link a un tuo post nel testo social: chip a video, `[canale url id=N]` nel
 * testo salvato (renderText), sostituito dall'indirizzo vero all'uscita.
 */
export const PostLink = Node.create({
    name: 'postLink',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            id: { default: null, parseHTML: (dom) => Number(dom.getAttribute('data-id')) },
            channel: { default: null, parseHTML: (dom) => dom.getAttribute('data-channel') },
            title: { default: null, parseHTML: (dom) => dom.getAttribute('data-title') || null },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-post-link]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes, {
            'data-post-link': '',
            'data-id': node.attrs.id,
            'data-channel': node.attrs.channel,
        }), postLinkShortcode(node.attrs.channel, node.attrs.id)];
    },

    renderText({ node }) {
        return postLinkShortcode(node.attrs.channel, node.attrs.id);
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const dom = el('span', 'rte-chip');
            dom.contentEditable = 'false';
            dom.title = 'Diventerà il link a questo post quando il post esce';
            dom.append(el('span', null, `🔗 ${describePostLink(postLinkShortcode(node.attrs.channel, node.attrs.id), node.attrs.title)}`));
            dom.append(removeButton(editor, getPos, node));
            return { dom };
        };
    },
});
