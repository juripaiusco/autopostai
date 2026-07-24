"""Contratto delle strategie di risposta automatica ai commenti.

Porting di `task/reply/*.py` (v1). Ogni canale costruisce il prompt per il LLM a
partire dal commento, poi invia la risposta generata tramite il provider.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass


@dataclass
class ReplyResult:
    remote_id: str
    text: str


class ReplyChannel(ABC):
    key: str = ""

    def __init__(self, comment: dict):
        self.comment = comment

    @abstractmethod
    def build_prompt(self) -> str:
        """Prompt per il LLM, formulato in base al tono/etichetta del canale (v1
        aveva una frase leggermente diversa per Facebook/Instagram/LinkedIn)."""

    @abstractmethod
    def send(self, reply_text: str) -> ReplyResult:
        """Invia la risposta al provider e ritorna id/testo effettivamente pubblicati."""

    def simulate(self, reply_text: str) -> ReplyResult:
        return ReplyResult(remote_id=f"dry-reply-{self.comment['id']}", text=reply_text)
