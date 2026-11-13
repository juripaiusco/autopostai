/**
 * HTML personalizzato della newsletter di un post (posts.nl_template_custom).
 * Stesse regole del worker (publisher/publishing/newsletter.py): [content] =
 * testo del post, [image N] = immagine N del post. Senza [content] l'email è
 * tutta nel template e il testo del post non serve.
 */

export const IMAGE_SHORTCODE = /\[image\s+(\d+)\]/gi;

const IMG_STYLE = 'max-width:100%; height:auto; display:block; margin: 0 auto;';

export function usesContent(template) {
    return String(template ?? '').includes('[content]');
}

/** Template impostato (anche vuoto mentre si scrive) = modalità HTML personalizzato. */
export function hasCustomTemplate(form) {
    return !!form.channels?.newsletter && form.nl_template_custom !== null && form.nl_template_custom !== undefined;
}

/**
 * Serve un testo del post? No solo per una newsletter da sola con template
 * senza [content] (Post::needsCommonText lato server).
 */
export function needsCommonText(form) {
    const ids = Object.keys(form.channels ?? {});
    if (!hasCustomTemplate(form) || ids.length !== 1) return true;
    return usesContent(form.nl_template_custom);
}

function escapeAttr(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

/**
 * HTML dell'email per l'anteprima (iframe sandbox): template + corpo + immagini.
 * [cta] e cross-link restano come shortcode (li risolve il worker all'invio).
 */
export function renderNewsletterPreview(template, bodyHtml, imageUrls = []) {
    const source = String(template ?? '');
    let body = String(bodyHtml ?? '');
    IMAGE_SHORTCODE.lastIndex = 0;
    if (imageUrls.length && !IMAGE_SHORTCODE.test(source)) {
        body = `<img src="${escapeAttr(imageUrls[0])}" style="width:100%; max-width:600px; height:auto; display:block; margin: 0 auto;"><br>${body}`;
    }
    return source
        .replace(IMAGE_SHORTCODE, (_, n) => {
            const url = imageUrls[Number(n) - 1];
            return url ? `<img src="${escapeAttr(url)}" alt="" style="${IMG_STYLE}">` : '';
        })
        .replaceAll('[content]', body)
        .replaceAll('[unsubscribe]', '#');
}
