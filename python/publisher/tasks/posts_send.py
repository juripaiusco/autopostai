"""Task: pubblica i post schedulati e accoda la notifica "post inviato".

Porting ottimizzato di `task/posts_send.py` (v1). Flusso per ogni post scaduto:
  1. per ogni canale acceso e non ancora pubblicato: genera/riusa il contenuto e pubblica;
  2. salva id/url remoti nel JSON channels;
  3. se TUTTI i canali accesi hanno un id => marca il post published e accoda la notifica.

Un canale che fallisce viene semplicemente saltato (l'errore e' isolato): resta
senza id e verra' ritentato al run successivo, senza bloccare gli altri canali/post.
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.content import ContentService
from publisher.db.repositories import PostRepository, PushNotificationRepository, TokenLogRepository
from publisher.domain.channels import Channels
from publisher.notifications import notify_post_published
from publisher.publishing import PUBLISHERS

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")

    post_repo = PostRepository(conn)
    push_repo = PushNotificationRepository(conn)
    content_service = ContentService(post_repo, TokenLogRepository(conn))

    posts = post_repo.due_posts(now)
    log.info("posts_send: %d post da valutare", len(posts))

    for post in posts:
        _send_one(post, post_repo, push_repo, content_service, now)


def _send_one(post, post_repo, push_repo, content_service, now) -> None:
    channels = Channels.parse(post["channels"])
    resolver = _url_resolver(post_repo, post["user_id"])

    for entry in channels.publishable():
        publisher = PUBLISHERS[entry.key](post, url_resolver=resolver)
        try:
            content = content_service.resolve(post, publisher, resolver)
            result = publisher.simulate() if config.DRY_RUN else publisher.publish(content)
        except Exception:  # noqa: BLE001 — un canale non deve far cadere gli altri
            log.exception("posts_send: canale '%s' fallito per il post %s", entry.key, post["id"])
            continue
        entry.set_result(result.remote_id, result.url, result.gallery_html)

    post_repo.save_channels(post["id"], channels.to_dict())

    # Post pubblicato solo se ogni canale acceso ha un id remoto.
    if channels.all_on_published():
        post_repo.mark_published(post["id"])
        if notify_post_published(push_repo, post, now):
            log.info("posts_send: post %s pubblicato, notifica accodata", post["id"])
        else:
            log.info("posts_send: post %s pubblicato (autore senza subscription push)", post["id"])


def _url_resolver(post_repo: PostRepository, requesting_user_id: int):
    """Risolve `[Canale url id=N]`: l'URL del post N su quel canale, solo se il
    post appartiene allo stesso utente che sta pubblicando (no leak tra account)."""

    def resolve(target_post_id: int, channel_key: str, _data_type: str) -> str:
        row = post_repo.channels_of(target_post_id)
        if not row or row["user_id"] != requesting_user_id:
            return "[URL non disponibile]"
        entry = Channels.parse(row["channels"]).entry(channel_key)
        if entry and entry.is_on and entry.already_published:
            return entry.data.get("url") or "[URL non ancora disponibile]"
        return "[URL non ancora disponibile]"

    return resolve
