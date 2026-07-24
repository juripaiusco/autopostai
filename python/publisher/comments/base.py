"""Contratto delle strategie di fetch commenti per-canale.

Porting di `task/comments/*.py` (v1). Il dedup e il salvataggio sono ora
responsabilita' di CommentRepository (query centralizzata), non della strategia:
qui si resta focalizzati sulla chiamata al provider e la normalizzazione dei dati.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass

from publisher import config


@dataclass
class FetchedComment:
    from_id: str | None
    from_name: str | None
    message_id: str
    message: str
    created_time: str  # 'YYYY-MM-DD HH:MM:SS'


class CommentFetcher(ABC):
    key: str = ""

    def __init__(self, post: dict, remote_post_id: str):
        self.post = post
        self.remote_post_id = remote_post_id

    @abstractmethod
    def fetch(self) -> list[FetchedComment]:
        """Recupera i commenti dal provider. In DRY_RUN i chiamanti evitano di
        invocare questo metodo (nessuna chiamata esterna nemmeno simulata: non
        c'e' nulla da salvare senza un post remoto reale)."""

    def simulate(self) -> list[FetchedComment]:
        return [
            FetchedComment(
                from_id="dry-user",
                from_name="Dry Run",
                message_id=f"dry-{self.key}-{self.post['id']}",
                message="[DRY_RUN] commento simulato",
                created_time=_now(),
            )
        ]


def _now() -> str:
    from datetime import datetime

    return datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
