"""Task (SCHELETRO): raccoglie i nuovi commenti dai canali social.

Porting da: v1-reference/docker/autopostai/task/comments_get.py
Da implementare in un commit dedicato.

Contratto atteso (come v1):
  - SELECT posts pubblicati con task_complete=0 e (on_hold_until NULL o passato),
    LIMIT 1 (un post per run) — centralizzare in PostRepository.
  - per ogni canale con on + comments_enabled e conteggio commenti < cap:
    fetch dei commenti dal provider (integrations/meta|linkedin), dedup su
    (post_id, channel, message_id), INSERT nella tabella `comments`.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:  # noqa: ARG001 — firma stabile per il worker
    log.debug("comments_get: non ancora implementato (scheletro)")
