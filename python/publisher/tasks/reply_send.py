"""Task (SCHELETRO): genera e invia le risposte automatiche ai commenti.

Porting da: v1-reference/docker/autopostai/task/reply_send.py
Da implementare in un commit dedicato.

Contratto atteso (come v1):
  - SELECT un commento senza reply (comments.reply IS NULL) del cui post l'utente
    ha auto_reply_enabled, LIMIT 1 — centralizzare in CommentRepository.
  - genera la risposta col LLM (integrations/openai_text, type='reply') e la invia
    via provider; salva reply_id/reply/reply_created_time sul commento.
  - logga i token con type='reply', reference_id=comment.id (tabella token_logs).
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:  # noqa: ARG001
    log.debug("reply_send: non ancora implementato (scheletro)")
