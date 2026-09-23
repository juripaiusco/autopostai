"""Task: rimuove i post remoti quando il post viene soft-deleted.

Porting di `task/posts_delete.py` (v1). Per ogni canale acceso chiama la delete del
provider e salva `id_del`; il post e' marcato deleted=1 solo quando ogni canale
acceso ha `id_del == id` (porting di ctrl_posts_deleted). Instagram non supporta la
cancellazione via API (come in v1: si limita a ricopiare l'id); idem Brevo e
newsletter smtp_custom (email gia' consegnate, nessuna piattaforma remota).
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.db.engine import checkpoint
from publisher.db.repositories import PostRepository
from publisher.domain.channels import CHANNEL_KEYS, ChannelEntry, Channels
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
                entry.data["id_del"] = _delete_on_channel(entry, post)
                log.info("posts_delete: post %s canale '%s' - rimosso (id=%s)", post["id"], key, entry.data["id_del"])
            except Exception:  # noqa: BLE001
                log.exception("posts_delete: delete '%s' fallita per il post %s", key, post["id"])

        post_repo.save_channels(post["id"], channels.to_dict())
        deleted = _ctrl_deleted(post_repo, post["id"], channels)
        checkpoint(conn)
        log.info("posts_delete: post %s - deleted=%s", post["id"], deleted)


def _delete_on_channel(entry: ChannelEntry, post: dict) -> str | None:
    key, remote_id = entry.key, entry.data["id"]
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
        # Provider dal canale, non dalle chiavi settings: un account smtp_custom
        # con anche una chiave Mailchimp tentava la delete Mailchimp sull'id
        # sintetico, e smtp_custom non aveva ramo (None -> mai deleted=1).
        provider = entry.resolve_newsletter_provider(post)
        if provider == "mailchimp":
            return Mailchimp(post["nl_mailchimp_api"], post["nl_mailchimp_datacenter"]).delete(remote_id)
        if provider in ("brevo", "smtp_custom"):
            # Brevo: l'API non offre una delete di campagna (v1 ricopiava l'id,
            # comportamento intenzionale). smtp_custom: email gia' consegnate, nessuna
            # piattaforma da avvisare — l'invio residuo si ferma gia' da solo
            # (due_smtp_custom_posts esclude i post soft-deleted).
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
