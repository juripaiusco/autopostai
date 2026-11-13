<?php

namespace App\Services;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Pulisce l'HTML di un template email scritto dall'utente (template
 * newsletter del singolo post). A differenza di PostHtmlSanitizer non
 * restringe i tag: un'email vive di tabelle, <style> e stili inline, che
 * devono restare. Toglie solo ciò che esegue codice o carica altre pagine:
 * script, iframe/oggetti, form, <base>, attributi on* e URL javascript:,
 * vbscript:, data: (fuori dalle immagini).
 *
 * L'anteprima nell'app è comunque in un iframe sandbox senza script: questa
 * pulizia protegge l'email che esce verso i contatti.
 *
 * Un frammento (senza <html>) torna frammento: niente html/head/body
 * aggiunti dal parser.
 */
class EmailHtmlSanitizer
{
    private const DROP_TAGS = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'base'];

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'background', 'poster', 'xlink:href'];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        foreach (self::DROP_TAGS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->remove();
            }
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            $this->cleanAttributes($element);
        }

        // Documento completo: serializzato così com'è (doctype solo se c'era).
        if (preg_match('/<html[\s>]/i', $html)) {
            return $document->saveHtml();
        }

        // Frammento: quello che il parser ha spostato in head (<style>, <meta>) + il body.
        return $this->children($document, $document->head).$this->children($document, $document->body);
    }

    private function cleanAttributes(Element $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (str_starts_with($name, 'on') || $name === 'srcdoc') {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && $this->unsafeUrl($attribute->value, $element->localName)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    private function unsafeUrl(string $value, string $tag): bool
    {
        // I browser ignorano spazi e caratteri di controllo dentro lo schema ("java\tscript:").
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

        if (str_starts_with($normalized, 'data:')) {
            return !($tag === 'img' && str_starts_with($normalized, 'data:image/') && !str_starts_with($normalized, 'data:image/svg'));
        }

        return str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:');
    }

    private function children(HTMLDocument $document, ?Element $element): string
    {
        if (!$element) {
            return '';
        }

        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $document->saveHtml($child);
        }

        return $html;
    }
}
