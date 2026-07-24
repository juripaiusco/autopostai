"""Task: genera e invia una risposta automatica a un commento in attesa.

Porting di `task/reply_send.py` (v1), adattato allo split v2 comments_enabled/
auto_reply_enabled: si esamina un piccolo batch di commenti senza risposta (non un
solo LIMIT 1 come v1) e si elabora il primo il cui canale ha auto_reply_enabled
attivo sul post — un commento con solo comments_enabled (tracciato ma senza
auto-risposta) viene lasciato stare e non blocca la coda per gli altri.
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.content import ContentService
from publisher.db.repositories import CommentRepository, PostRepository, TokenLogRepository
from publisher.domain.channels import Channels
from publisher.replies import REPLIERS

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")

    comment_repo = CommentRepository(conn)
    content_service = ContentService(PostRepository(conn), TokenLogRepository(conn))

    for comment in comment_repo.due_for_reply(limit=config.REPLY_SEND_BATCH_SIZE):
        entry = Channels.parse(comment["channels"]).entry(comment["channel"])
        if not entry or not entry.auto_reply_enabled:
            continue

        replier_cls = REPLIERS.get(comment["channel"])
        if replier_cls is None:
            continue

        replier = replier_cls(comment)
        try:
            reply_text = content_service.generate_reply(comment, replier.build_prompt())
            result = replier.simulate(reply_text) if config.DRY_RUN else replier.send(reply_text)
        except Exception:  # noqa: BLE001
            log.exception("reply_send: risposta fallita per il commento %s", comment["id"])
            return

        if not result.remote_id:
            log.warning("reply_send: provider non ha restituito un id per il commento %s", comment["id"])
            return

        comment_repo.mark_replied(comment["id"], result.remote_id, result.text, now)
        log.info("reply_send: risposto al commento %s (canale %s)", comment["id"], comment["channel"])
        return  # un solo reply per run, come v1

    log.debug("reply_send: nessun commento idoneo nel batch")
