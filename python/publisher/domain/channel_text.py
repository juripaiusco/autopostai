"""Adatta il testo del post al canale su cui esce.

Il testo AI e' uno per post (`posts.ai_content`). Se tra i canali accesi c'e'
WordPress o Newsletter e' in Markdown (titolo `#`, `**grassetto**`, link): va
bene per l'articolo e per l'email, ma sui social usciva con i simboli letterali.
Qui i social ricevono la versione in testo semplice; i link restano come URL.

Lo shortcode `[cta ...]` ha un template HTML solo per la newsletter (Account →
Newsletter). Sugli altri canali diventa un link semplice (WordPress) o
"testo: url" (social), invece di uscire come testo grezzo.
"""

from __future__ import annotations

import html as html_lib
import re

import markdown as markdown_lib

from publisher.integrations import shortcodes

SOCIAL_KEYS = ("facebook", "instagram", "linkedin")

CTA_SOCIAL_TEMPLATE = "[text]: [url]"
CTA_LINK_TEMPLATE = '<a href="[url]">[text]</a>'


def html_to_text(html: str) -> str:
    """Testo leggibile da un html: link come "testo (url)" (solo l'url se il
    testo e' l'url stesso), blocchi su righe separate, niente tag/stili/pixel."""
    text = re.sub(r"(?is)<(style|script|head)\b.*?</\1>", "", html)
    text = re.sub(r'(?is)<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', _link_as_text, text)
    text = re.sub(r"(?i)<br\s*/?>", "\n", text)
    text = re.sub(r"(?i)</(p|div|h[1-6]|li|tr|table|blockquote)>", "\n\n", text)
    text = re.sub(r"<[^>]+>", "", text)
    text = html_lib.unescape(text)
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n\s*\n\s*(\n\s*)+", "\n\n", text)
    return "\n".join(line.strip() for line in text.strip().splitlines())


def _link_as_text(match: re.Match) -> str:
    url = match.group(1)
    label = re.sub(r"<[^>]+>", "", match.group(2)).strip()
    if not label or label == url:
        return url
    return f"{label} ({url})"


def markdown_to_text(md: str) -> str:
    """Markdown → testo semplice (il titolo `#` resta come prima riga)."""
    return html_to_text(markdown_lib.markdown(md or ""))


def for_channel(text: str, key: str, url_resolver, markdown: bool) -> str:
    """Testo pronto per il canale `key`. `markdown` = il testo e' quello AI in
    Markdown (post con WordPress/Newsletter accesi); un testo scritto apposta per
    il canale (modifica dopo la pubblicazione) non va riconvertito."""
    if key in SOCIAL_KEYS:
        text = shortcodes.parse_cta(text, CTA_SOCIAL_TEMPLATE, url_resolver)
        return markdown_to_text(text) if markdown else text
    if key == "wordpress":
        return shortcodes.parse_cta(text, CTA_LINK_TEMPLATE, url_resolver)
    return text
