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
from publisher.integrations.unsubscribe import unsubscribe_url
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
        # La riga va creata PRIMA di inviare: il pixel di tracking apertura
        # (Step 8) dentro l'html porta l'id di questa riga, quindi deve
        # esistere gia' quando l'email parte.
        send_id = contact_repo.create_placeholder(contact["id"], post["id"], now)
        contact_html = _finalize_html(html, contact["id"], send_id)
        try:
            if not config.DRY_RUN:
                client.send(contact["email"], subject, contact_html, post["nl_smtp_sender"], post["nl_smtp_username"])
            contact_repo.mark_sent(send_id, now)
            sent += 1
        except Exception as e:  # noqa: BLE001 — un bounce non deve bloccare gli altri contatti
            contact_repo.mark_send_bounced(send_id, contact["id"], now, str(e))
            bounced += 1

    log.info("newsletter_send: post %s - batch inviato (%d ok, %d bounce)", post["id"], sent, bounced)

    # Step 8: l'aggregato in channels.newsletter.stats si ricalcola ad ogni
    # batch (non solo al primo) — stessa struttura/naming per ogni provider
    # newsletter, cosi' il frontend che gia' legge channels non ha bisogno di
    # logica dedicata. entry.data e' lo stesso dict di channels_dict['newsletter']
    # (riferimento, non copia): set_result() qui sotto si riflette in entrambi.
    first_batch = not entry.already_published
    if first_batch:
        entry.set_result(f"smtp-{post['id']}", None)

    channels_dict = channels.to_dict()
    channels_dict["newsletter"]["stats"] = contact_repo.stats_for_post(post["id"])
    post_repo.save_channels(post["id"], channels_dict)

    if first_batch and channels.all_on_published():
        post_repo.mark_published(post["id"])
        log.info("newsletter_send: post %s pubblicato (primo batch inviato)", post["id"])


def _finalize_html(html: str, contact_id: int, send_id: int) -> str:
    """Link di disiscrizione per-contatto (Step 7) + pixel di tracking
    apertura per-invio (Step 8), entrambi aggiunti allo stesso html condiviso
    del batch prima di spedirlo a QUESTO contatto."""
    url = unsubscribe_url(contact_id)
    if "[unsubscribe]" in html:
        html = html.replace("[unsubscribe]", url)
    else:
        html += (
            '<p style="font-size:12px;color:#9ca3af;text-align:center;margin-top:24px">'
            f'<a href="{url}" style="color:#9ca3af">Disiscriviti</a></p>'
        )

    pixel = f'<img src="{config.APP_URL}/pixel/{send_id}.gif" width="1" height="1" alt="" style="display:none">'
    return html + pixel


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
