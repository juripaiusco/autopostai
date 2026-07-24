"""Task: rimuove i post remoti quando il post viene soft-deleted.

Porting di `task/posts_delete.py` (v1). Per ogni canale acceso chiama la delete del
provider e salva `id_del`; il post e' marcato deleted=1 solo quando ogni canale
acceso ha `id_del == id` (porting di ctrl_posts_deleted). Instagram non supporta la
cancellazione via API (come in v1: si limita a ricopiare l'id) e Brevo idem.
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.db.repositories import PostRepository
from publisher.domain.channels import CHANNEL_KEYS, Channels
from publisher.integrations.linkedin import LinkedIn
from publisher.integrations.mailchimp import Mailchimp
from publisher.integrations.meta import Meta
from publisher.integrations.wordpress import WordPress

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    post_repo = PostRepository(conn)

    posts = post_repo.due_for_deletion(_now())
    if not posts:
        log.info("posts_delete: nessun post da eliminare")
        return

    for post in posts:
        log.info("posts_delete: post %s selezionato per la rimozione", post["id"])
        channels = Channels.parse(post["channels"])

        for key in CHANNEL_KEYS:
            entry = channels.entry(key)
            if not entry or not entry.is_on or not entry.already_published:
                continue
            if entry.data.get("id") == entry.data.get("id_del"):
                continue  # gia' rimosso in un run precedente

            try:
                entry.data["id_del"] = _delete_on_channel(key, post, entry.data["id"])
                log.info("posts_delete: post %s canale '%s' - rimosso (id=%s)", post["id"], key, entry.data["id_del"])
            except Exception:  # noqa: BLE001
                log.exception("posts_delete: delete '%s' fallita per il post %s", key, post["id"])

        post_repo.save_channels(post["id"], channels.to_dict())
        deleted = _ctrl_deleted(post_repo, post["id"], channels)
        log.info("posts_delete: post %s - deleted=%s", post["id"], deleted)


def _delete_on_channel(key: str, post: dict, remote_id: str) -> str | None:
    if config.DRY_RUN:
        return remote_id

    if key == "facebook":
        return Meta(post["meta_page_id"], config.META_USER_ACCESS_TOKEN).fb_delete(remote_id)
    if key == "instagram":
        return Meta(post["meta_page_id"], config.META_USER_ACCESS_TOKEN).ig_delete(remote_id)
    if key == "linkedin":
        return LinkedIn(token=post["linkedin_token"], company_id=post["linkedin_company_id"]).delete(remote_id)
    if key == "wordpress":
        return WordPress(post["wordpress_url"], post["wordpress_username"], post["wordpress_password"]).delete(remote_id)
    if key == "newsletter":
        if post.get("nl_mailchimp_api"):
            return Mailchimp(post["nl_mailchimp_api"], post["nl_mailchimp_datacenter"]).delete(remote_id)
        if post.get("nl_brevo_api"):
            # L'API Brevo non offre una delete di campagna: v1 si limitava a
            # ricopiare l'id (comportamento intenzionale, non un TODO).
            return remote_id
    return None


def _ctrl_deleted(post_repo: PostRepository, post_id: int, channels: Channels) -> str:
    all_deleted = True
    for key in CHANNEL_KEYS:
        entry = channels.entry(key)
        if entry and entry.is_on and entry.data.get("id") != entry.data.get("id_del"):
            all_deleted = False

    value = "1" if all_deleted else "0"
    post_repo.set_deleted(post_id, value)
    return value


def _now() -> str:
    return datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
