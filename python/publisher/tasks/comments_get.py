"""Task: raccoglie i nuovi commenti dai canali social.

Porting di `task/comments_get.py` (v1). Un post per run (come v1): il piu' vecchio
tra quelli pubblicati, non ancora task_complete, fuori dal backoff. Per ogni canale
social che richiede monitoraggio (acceso, comments_enabled, sotto al cap reply_n
congelato nel post) fetcha i commenti dal provider e li salva (dedup su
post_id+channel+message_id via CommentRepository).
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.comments import FETCHERS
from publisher.db.repositories import CommentRepository, PostRepository
from publisher.domain.channels import Channels

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now_dt = datetime.now(config.LOCAL_TIMEZONE)
    now = now_dt.strftime("%Y-%m-%d %H:%M:%S")

    post_repo = PostRepository(conn)
    comment_repo = CommentRepository(conn)

    post = post_repo.due_for_comment_monitoring(now)
    if post is None:
        log.info("comments_get: nessun post da monitorare")
        return

    log.info("comments_get: post %s selezionato per il monitoraggio commenti", post["id"])

    channels = Channels.parse(post["channels"])
    counts = {
        "facebook": post.get("facebook_comments_count") or 0,
        "instagram": post.get("instagram_comments_count") or 0,
        "linkedin": post.get("linkedin_comments_count") or 0,
    }

    for key, fetcher_cls in FETCHERS.items():
        entry = channels.entry(key)
        if not entry or not entry.needs_comment_fetch(counts[key]):
            continue

        remote_post_id = entry.data.get("id")
        if not remote_post_id:
            continue

        fetcher = fetcher_cls(post, remote_post_id)
        try:
            fetched = fetcher.simulate() if config.DRY_RUN else fetcher.fetch()
        except Exception:  # noqa: BLE001 — un canale non deve bloccare gli altri
            log.exception("comments_get: fetch '%s' fallito per il post %s", key, post["id"])
            continue

        for comment in fetched:
            comment_repo.save(
                post_id=post["id"],
                channel=key,
                from_id=comment.from_id,
                from_name=comment.from_name,
                message_id=comment.message_id,
                message=comment.message,
                message_created_time=comment.created_time,
                now=now,
            )

        log.info("comments_get: post %s canale '%s' - %d commenti importati", post["id"], key, len(fetched))
