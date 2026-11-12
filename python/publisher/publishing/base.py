"""Contratto delle strategie di pubblicazione per-canale.

Riprende la struttura "base class + una sottoclasse per canale" di v1 (il suo
punto di forza: seam auto-documentante per aggiungere canali) ripulendola.
Ogni canale sa: (1) costruire il proprio prompt LLM, (2) pubblicare un contenuto.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass

import html as html_lib
import re

import markdown as markdown_lib

from publisher import config

_H1 = re.compile(r"(?is)<h1\b[^>]*>(.*?)</h1>")


def split_markdown(md_content: str) -> tuple[str, str]:
    """Separa la prima riga (# titolo) dal corpo, rendendo il corpo in HTML.

    Usato da WordPress e Newsletter, che generano contenuto Markdown con un titolo.
    """
    lines = (md_content or "").strip().split("\n", 1)
    title = lines[0].replace("#", "").strip()
    body = lines[1].strip() if len(lines) > 1 else ""
    return title, markdown_lib.markdown(body)


def split_html(html: str) -> tuple[str, str]:
    """Come split_markdown per il testo dell'editor ricco (gia' HTML): il
    titolo e' il primo `<h1>`, il corpo e' il resto, senza riconversioni.
    Senza `<h1>` il titolo e' vuoto."""
    html = (html or "").strip()
    match = _H1.search(html)
    if not match:
        return "", html
    title = html_lib.unescape(re.sub(r"<[^>]+>", "", match.group(1))).strip()
    body = (html[: match.start()] + html[match.end():]).strip()
    return title, body


def split_content(content: str, fmt: str | None) -> tuple[str, str]:
    """Titolo + corpo HTML del testo articolo secondo il suo formato."""
    return split_html(content) if fmt == "html" else split_markdown(content)


@dataclass
class PublishResult:
    remote_id: str
    url: str
    gallery_html: str | None = None


class ChannelPublisher(ABC):
    #: chiave del canale nel JSON channels (facebook, wordpress, ...)
    key: str = ""
    #: True se il prompt di questo canale va arricchito col contenuto delle
    #: pagine citate (integrations/web_pages): articoli e newsletter, non i social.
    reads_urls: bool = False

    def __init__(self, post: dict, url_resolver=None):
        self.post = post
        # Iniettato dall'orchestratore: risolve gli URL degli shortcode CTA.
        # Serve solo ad alcuni canali (Newsletter), gli altri lo ignorano.
        self.url_resolver = url_resolver

    def split(self, content: str) -> tuple[str, str]:
        """Titolo + corpo HTML del testo comune del post (WordPress, newsletter).
        Senza titolo (HTML senza `<h1>`) vale il titolo interno del post."""
        title, body = split_content(content, self.post.get("ai_content_format"))
        return title or (self.post.get("title") or ""), body

    def build_prompt(self) -> str:
        """Prompt di default: le sole istruzioni dell'utente. I canali che
        richiedono un formato specifico (WordPress/Newsletter -> Markdown) fanno override."""
        return self.post.get("ai_prompt_post") or ""

    @abstractmethod
    def publish(self, content: str) -> PublishResult:
        """Pubblica il contenuto e ritorna id/url remoti."""

    def simulate(self) -> PublishResult:
        """Risultato finto per DRY_RUN: nessuna chiamata esterna."""
        pid = f"dry-{self.key}-{self.post['id']}"
        return PublishResult(pid, f"{config.APP_URL}/dry-run/{self.key}/{self.post['id']}")
