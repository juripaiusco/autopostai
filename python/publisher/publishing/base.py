"""Contratto delle strategie di pubblicazione per-canale.

Riprende la struttura "base class + una sottoclasse per canale" di v1 (il suo
punto di forza: seam auto-documentante per aggiungere canali) ripulendola.
Ogni canale sa: (1) costruire il proprio prompt LLM, (2) pubblicare un contenuto.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass

import markdown as markdown_lib

from publisher import config


def split_markdown(md_content: str) -> tuple[str, str]:
    """Separa la prima riga (# titolo) dal corpo, rendendo il corpo in HTML.

    Usato da WordPress e Newsletter, che generano contenuto Markdown con un titolo.
    """
    lines = (md_content or "").strip().split("\n", 1)
    title = lines[0].replace("#", "").strip()
    body = lines[1].strip() if len(lines) > 1 else ""
    return title, markdown_lib.markdown(body)


@dataclass
class PublishResult:
    remote_id: str
    url: str
    gallery_html: str | None = None


class ChannelPublisher(ABC):
    #: chiave del canale nel JSON channels (facebook, wordpress, ...)
    key: str = ""

    def __init__(self, post: dict, url_resolver=None):
        self.post = post
        # Iniettato dall'orchestratore: risolve gli URL degli shortcode CTA.
        # Serve solo ad alcuni canali (Newsletter), gli altri lo ignorano.
        self.url_resolver = url_resolver

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
