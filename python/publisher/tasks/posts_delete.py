"""Task (SCHELETRO): rimuove i post remoti quando il post viene soft-deleted.

Porting da: v1-reference/docker/autopostai/task/posts_delete.py
Da implementare in un commit dedicato.

Contratto atteso (come v1):
  - SELECT post con published=1, deleted=0 e deleted_at passato.
  - elimina il post su ogni canale (FB/LinkedIn/WordPress/Mailchimp); imposta
    deleted=1 quando tutti i canali risultano rimossi.
Nota: Instagram non supporta la delete via API (v1 ricopiava solo l'id) e Brevo
idem — replicare quel comportamento.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:  # noqa: ARG001
    log.debug("posts_delete: non ancora implementato (scheletro)")
