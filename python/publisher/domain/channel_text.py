"""Adatta il testo del post al canale su cui esce.

Il testo e' uno per post (`posts.ai_content`). Se tra i canali accesi c'e'
WordPress o Newsletter e' l'articolo: Markdown (testo AI, `ai_content_format =
markdown`) o HTML (editor ricco, `html`). Va bene per l'articolo e per l'email,
ma sui social usciva con i simboli letterali: qui i social ricevono il testo
personalizzato (`ai_content_social`) se c'e', altrimenti l'articolo in testo
semplice, con i link come URL.

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


def source_format(post: dict, channels) -> str:
    """Formato del testo comune ai fini della conversione: senza canali
    articolo e' il testo dei social (anche se la colonna dice `markdown`, il
    default dei post precedenti all'editor)."""
    if channels.text_channel() is None:
        return "text"
    return post.get("ai_content_format") or "markdown"


def custom_social_text(post: dict) -> str | None:
    """Testo social scritto/generato apposta (`ai_content_social`), se c'e'."""
    social = post.get("ai_content_social")
    return social if (social or "").strip() else None


def for_channel(text: str, key: str, url_resolver, fmt: str) -> str:
    """Testo pronto per il canale `key`. `fmt` = formato di `text`
    (markdown/html/text): sui social markdown e html diventano testo semplice,
    un testo scritto apposta per il canale (`text`) esce com'e'."""
    if key in SOCIAL_KEYS:
        text = shortcodes.parse_cta(text, CTA_SOCIAL_TEMPLATE, url_resolver)
        if fmt == "markdown":
            return markdown_to_text(text)
        if fmt == "html":
            return html_to_text(text)
        return text
    if key == "wordpress":
        return shortcodes.parse_cta(text, CTA_LINK_TEMPLATE, url_resolver)
    return text


def for_post_channel(post: dict, channels, key: str, content: str, url_resolver) -> str:
    """Testo del post per il canale `key` alla pubblicazione. `content` e' il
    testo comune gia' risolto (ContentService.resolve)."""
    social = custom_social_text(post) if key in SOCIAL_KEYS else None
    if social is not None:
        return for_channel(shortcodes.parse_crosslinks(social, url_resolver), key, url_resolver, "text")
    return for_channel(content, key, url_resolver, source_format(post, channels))
