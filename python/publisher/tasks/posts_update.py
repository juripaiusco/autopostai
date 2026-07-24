"""Task: ripubblica i post modificati (updated=2).

Porting di `task/posts_update.py` (v1). Come in v1, **solo l'update WordPress e'
implementato**: Facebook/Instagram non supportano bene l'update via API (v1 li
aveva gia' commentati) — restano TODO espliciti, non un'omissione silenziosa.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

from publisher import config
from publisher.db.repositories import PostRepository
from publisher.domain.channels import CHANNEL_KEYS, Channels
from publisher.integrations.wordpress import WordPress
from publisher.publishing.base import split_markdown

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    post_repo = PostRepository(conn)

    post = post_repo.due_for_update()
    if post is None:
        log.debug("posts_update: nessun post da aggiornare")
        return

    channels = Channels.parse(post["channels"])

    # TODO(facebook/instagram): v1 aveva gia' questi update commentati (l'API
    # Graph non si presta bene a un edit pulito del post pubblicato). Se servira'
    # in futuro, seguire lo stesso pattern di WordPress qui sotto.
    entry = channels.entry("wordpress")
    if entry and entry.is_on and entry.already_published:
        title, body = split_markdown(post.get("ai_content") or "")
        try:
            new_id = _wordpress_update(post, entry.data["id"], title, body)
            entry.data["id_update"] = new_id
        except Exception:  # noqa: BLE001
            log.exception("posts_update: update WordPress fallito per il post %s", post["id"])

    post_repo.save_channels(post["id"], channels.to_dict())
    _ctrl_updated(post_repo, post["id"], channels)


def _wordpress_update(post: dict, post_id: str, title: str, body: str) -> str | None:
    if config.DRY_RUN:
        return post_id
    wp = WordPress(url=post["wordpress_url"], username=post["wordpress_username"], password=post["wordpress_password"])
    return wp.update(post_id, title, body)


def _ctrl_updated(post_repo: PostRepository, post_id: int, channels: Channels) -> None:
    """updated=1 solo se OGNI canale acceso ha id_update coincidente con id
    (porting di ctrl_posts_update). Canali senza update implementato (facebook/
    instagram, se accesi) restano senza id_update per sempre: come in v1, quei
    post non arrivano mai a updated=1 finche' quei canali non vengono spenti."""
    all_updated = True
    for key in CHANNEL_KEYS:
        entry = channels.entry(key)
        if entry and entry.is_on and entry.data.get("id") != entry.data.get("id_update"):
            all_updated = False

    post_repo.set_updated(post_id, "1" if all_updated else "0")
