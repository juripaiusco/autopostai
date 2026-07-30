"""Task: invia la newsletter smtp_custom ai Contact interni, a batch throttled.

A differenza degli altri canali (una singola chiamata API = pubblicato) l'invio
smtp_custom si spalma su piu' run: ogni tick manda fino a
NEWSLETTER_SMTP_BATCH_SIZE contatti NUOVI per account (i gia' processati per
questo post — sent o bounced — non vengono ripescati, vedi
ContactRepository.sendable_for_post). Il post riceve un id sintetico sul canale
newsletter al PRIMO batch inviato (passa "Pubblicato" subito, la consegna
prosegue in background) — non e' un id remoto reale, nessuna piattaforma
esterna e' coinvolta.
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.content import ContentService
from publisher.db.repositories import ContactRepository, PostRepository, TokenLogRepository
from publisher.domain.channels import Channels
from publisher.integrations.smtp import SmtpClient
from publisher.publishing.newsletter import NewsletterPublisher

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")

    post_repo = PostRepository(conn)
    contact_repo = ContactRepository(conn)
    token_repo = TokenLogRepository(conn)
    content_service = ContentService(post_repo, token_repo)

    posts = post_repo.due_smtp_custom_posts(now)
    log.info("newsletter_send: %d post da valutare", len(posts))

    for post in posts:
        _process_one(post, post_repo, contact_repo, token_repo, content_service, now)


def _process_one(post, post_repo, contact_repo, token_repo, content_service, now) -> None:
    channels = Channels.parse(post["channels"])
    entry = channels.entry("newsletter")
    if not entry or not entry.is_on or entry.newsletter_provider() != "smtp_custom":
        return

    contacts = contact_repo.sendable_for_post(post["user_id"], post["id"], config.NEWSLETTER_SMTP_BATCH_SIZE)
    if not contacts:
        return

    # Stesso guard di posts_send.py: se il contenuto va ancora generato e
    # l'account ha esaurito i token, il post viene abbandonato senza inviare
    # (published=1 + task_complete=1, non piu' rivalutato da nessun task).
    if not post.get("ai_content") and token_repo.is_over_limit(post["user_id"]):
        post_repo.abandon_over_limit(post["id"])
        log.info("newsletter_send: post %s abbandonato (limite token superato)", post["id"])
        return

    publisher = NewsletterPublisher(post, url_resolver=_url_resolver(post_repo, post["user_id"]))
    try:
        content = content_service.resolve(post, publisher, publisher.url_resolver)
        subject, html = publisher.render(content)
    except Exception:  # noqa: BLE001 — un errore di rendering non deve bloccare gli altri post
        log.exception("newsletter_send: rendering fallito per il post %s", post["id"])
        return

    client = SmtpClient(
        post["nl_smtp_host"], post["nl_smtp_port"], post["nl_smtp_username"],
        post["nl_smtp_password"], post["nl_smtp_encryption"],
    )

    sent = 0
    bounced = 0
    for contact in contacts:
        try:
            if not config.DRY_RUN:
                client.send(contact["email"], subject, html, post["nl_smtp_sender"], post["nl_smtp_username"])
            contact_repo.record_send(contact["id"], post["id"], "sent", now)
            sent += 1
        except Exception as e:  # noqa: BLE001 — un bounce non deve bloccare gli altri contatti
            contact_repo.record_send(contact["id"], post["id"], "bounced", now, error_message=str(e))
            contact_repo.mark_bounced(contact["id"])
            bounced += 1

    log.info("newsletter_send: post %s - batch inviato (%d ok, %d bounce)", post["id"], sent, bounced)

    if not entry.already_published:
        entry.set_result(f"smtp-{post['id']}", None)
        post_repo.save_channels(post["id"], channels.to_dict())
        if channels.all_on_published():
            post_repo.mark_published(post["id"])
            log.info("newsletter_send: post %s pubblicato (primo batch inviato)", post["id"])


def _url_resolver(post_repo: PostRepository, requesting_user_id: int):
    """Stesso resolver di posts_send.py (duplicato: piccolo, non vale un modulo condiviso)."""

    def resolve(target_post_id: int, channel_key: str, _data_type: str) -> str:
        row = post_repo.channels_of(target_post_id)
        if not row or row["user_id"] != requesting_user_id:
            return "[URL non disponibile]"
        entry = Channels.parse(row["channels"]).entry(channel_key)
        if entry and entry.is_on and entry.already_published:
            return entry.data.get("url") or "[URL non ancora disponibile]"
        return "[URL non ancora disponibile]"

    return resolve
