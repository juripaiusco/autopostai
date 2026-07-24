"""Task (SCHELETRO): ripubblica i post modificati (updated=2).

Porting da: v1-reference/docker/autopostai/task/posts_update.py
Da implementare in un commit dedicato.

Contratto atteso (come v1):
  - SELECT post con updated=2 (in attesa di sync), LIMIT 1.
  - per ogni canale gia' pubblicato, aggiorna il contenuto remoto; imposta updated=1
    quando tutti i canali risultano aggiornati.
Nota: in v1 solo l'update WordPress era implementato (FB/IG commentati). Valutare
quali provider supportano davvero l'update prima di portarli.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:  # noqa: ARG001
    log.debug("posts_update: non ancora implementato (scheletro)")
