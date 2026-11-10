"""Task: rimanda alle piattaforme il testo dei post modificati (updated=2).

Porting di `task/posts_update.py` (v1), esteso. La pagina del post (Laravel,
PostController::updateChannelContent) salva il testo corretto nel singolo canale
(`channels.<c>.content`, assente = `ai_content` comune) e lo segna con
`update_pending`. Qui si aggiornano solo i canali che lo permettono via API:
WordPress e Facebook. Instagram non consente di cambiare la didascalia, una
newsletter inviata non si richiama; LinkedIn arrivera' con la Posts API.

Un aggiornamento fallito non viene ritentato da solo (stessa logica dei
tentativi in posts_send): l'errore finisce in `update_error` e l'utente rilancia
la modifica dal box del canale.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

from publisher import config
from publisher.db.repositories import PostRepository
from publisher.domain import channel_text
from publisher.domain.channels import Channels
from publisher.integrations import shortcodes
from publisher.integrations.meta import Meta
from publisher.integrations.wordpress import WordPress
from publisher.publishing.base import split_content
from publisher.tasks.posts_send import _error_message, _url_resolver

log = logging.getLogger(__name__)

#: canali aggiornabili dopo la pubblicazione (Post::EDITABLE_AFTER_PUBLISH in Laravel)
UPDATABLE = ("facebook", "wordpress")


def run(conn: Connection) -> None:
    post_repo = PostRepository(conn)

    post = post_repo.due_for_update()
    if post is None:
        log.info("posts_update: nessun post da aggiornare")
        return

    log.info("posts_update: post %s selezionato per il risync", post["id"])
    channels = Channels.parse(post["channels"])
    resolver = _url_resolver(post_repo, post["user_id"])

    for key in _keys_to_update(channels):
        entry = channels.entry(key)
        own_text = entry.data.get("content")
        if own_text:
            # Testo scritto apposta per il canale dal box "Modifica post":
            # su WordPress HTML dell'editor (content_format) o Markdown (MCP,
            # testi precedenti), sui social testo semplice.
            fmt = (entry.data.get("content_format") or "markdown") if key == "wordpress" else "text"
            text = channel_text.for_channel(shortcodes.parse_crosslinks(own_text, resolver), key, resolver, fmt)
        else:
            fmt = channel_text.source_format(post, channels)
            text = shortcodes.parse_crosslinks(post.get("ai_content") or "", resolver)
            text = channel_text.for_post_channel(post, channels, key, text, resolver)
        try:
            if not config.DRY_RUN:
                _UPDATERS[key](post, entry.data, text, fmt)
            entry.data["id_update"] = entry.data["id"]
            entry.data.pop("update_error", None)
            log.info("posts_update: post %s canale '%s' - aggiornato", post["id"], key)
        except Exception as exc:  # noqa: BLE001
            entry.data["update_error"] = _error_message(exc)
            log.exception("posts_update: update '%s' fallito per il post %s", key, post["id"])
        entry.data.pop("update_pending", None)

    post_repo.save_channels(post["id"], channels.to_dict())
    post_repo.set_updated(post["id"], "1")


def _keys_to_update(channels: Channels) -> list[str]:
    """Canali accesi, pubblicati e con update_pending. Post v1 (updated=2 senza
    update_pending, il vecchio pulsante "Modifica" di v1): solo WordPress, come in v1."""
    published = [
        key for key in UPDATABLE
        if (entry := channels.entry(key)) and entry.is_on and entry.already_published
    ]
    pending = [key for key in published if channels.entry(key).data.get("update_pending")]
    return pending or [key for key in published if key == "wordpress"]


def _wordpress_update(post: dict, data: dict, text: str, fmt: str) -> None:
    title, body = split_content(text, fmt)
    title = title or (post.get("title") or "")
    # La galleria (dalla seconda immagine in poi) era stata messa in testa al
    # contenuto in pubblicazione: senza rimetterla l'update la cancellerebbe.
    if data.get("gallery_html"):
        body = data["gallery_html"] + "<br>" + body
    wp = WordPress(url=post["wordpress_url"], username=post["wordpress_username"], password=post["wordpress_password"])
    wp.update(data["id"], title, body)


def _facebook_update(post: dict, data: dict, text: str, _fmt: str) -> None:
    meta = Meta(page_id=post["meta_page_id"], user_access_token=config.META_USER_ACCESS_TOKEN)
    if not meta.fb_update(data["id"], text):
        raise RuntimeError("Facebook non ha confermato l'aggiornamento")


_UPDATERS = {"wordpress": _wordpress_update, "facebook": _facebook_update}
