"""Task: invia la newsletter smtp_custom ai Contact interni, a batch throttled.

A differenza degli altri canali (una singola chiamata API = pubblicato) l'invio
smtp_custom si spalma su piu' run: ogni tick manda fino a
NEWSLETTER_SMTP_BATCH_SIZE contatti NUOVI per account (i gia' processati per
questo post — sent o bounced — non vengono ripescati, vedi
ContactRepository.sendable_for_post). Il post riceve un id sintetico sul canale
newsletter al PRIMO batch inviato (passa "Pubblicato" subito, la consegna
prosegue in background) — non e' un id remoto reale, nessuna piattaforma
esterna e' coinvolta.

Il pubblico e' congelato a `published_at`: i contatti registrati dopo non
ricevono newsletter gia' uscite. Quando non resta nessun contatto da servire il
canale riceve `completed_at` e il post esce dalla selezione (anche con 0
destinatari al primo giro: il post viene comunque marcato pubblicato, con stats
a zero, invece di restare published=0 ripescato ogni minuto da posts_send).
"""

from __future__ import annotations

import logging
from datetime import datetime

from sqlalchemy.engine import Connection

from publisher import config
from publisher.content import ContentService
from publisher.db.engine import checkpoint
from publisher.db.repositories import ContactRepository, PostRepository, TokenLogRepository
from publisher.domain.channels import Channels
from publisher.integrations.smtp import RecipientRefused, SmtpClient
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
        checkpoint(conn)


def _process_one(post, post_repo, contact_repo, token_repo, content_service, now) -> None:
    channels = Channels.parse(post["channels"])
    entry = channels.entry("newsletter")
    if not entry or not entry.is_on or entry.newsletter_provider() != "smtp_custom":
        return

    contacts = contact_repo.sendable_for_post(
        post["user_id"], post["id"], config.NEWSLETTER_SMTP_BATCH_SIZE,
        audience_cutoff=post["published_at"], tag_id=entry.newsletter_tag_id(),
    )
    if not contacts:
        _complete(post, channels, entry, post_repo, contact_repo, now)
        return

    # Stesso guard di posts_send.py: se il contenuto va ancora generato e
    # l'account ha esaurito i token, il post viene abbandonato senza inviare
    # (published=1 + task_complete=1, non piu' rivalutato da nessun task).
    # completed_at: senza, due_smtp_custom_posts lo ripescherebbe ogni minuto.
    if not post.get("ai_content") and token_repo.is_over_limit(post["user_id"]):
        post_repo.abandon_over_limit(post["id"])
        entry.data["completed_at"] = now
        post_repo.save_channels(post["id"], channels.to_dict())
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

    sent, bounced = _send_batch(client, contacts, post, subject, html, contact_repo, now)
    if not sent and not bounced:
        return  # batch interrotto prima del primo invio (gia' loggato): niente da salvare
    log.info("newsletter_send: post %s - batch inviato (%d ok, %d bounce)", post["id"], sent, bounced)

    # Step 8: l'aggregato in channels.newsletter.stats si ricalcola ad ogni
    # batch (non solo al primo) — stessa struttura/naming per ogni provider
    # newsletter, cosi' il frontend che gia' legge channels non ha bisogno di
    # logica dedicata. entry.data e' lo stesso dict di channels_dict['newsletter']
    # (riferimento, non copia): set_result() qui sotto si riflette in entrambi.
    if _save_progress(post, channels, entry, post_repo, contact_repo):
        log.info("newsletter_send: post %s pubblicato (primo batch inviato)", post["id"])


def _send_batch(client, contacts, post, subject, html, contact_repo, now) -> tuple[int, int]:
    """Invia a ogni contatto del batch. Bounce solo quando il server rifiuta il
    destinatario: 5xx = hard (contatto -> bounced), 4xx = soft (solo questo
    invio, contatto resta attivo). Ogni altro errore (connessione, login,
    mittente, timeout) e' dell'account, non del contatto: batch interrotto,
    placeholder rimosso, si riprova al giro dopo — prima marcava bounced ogni
    contatto e un SMTP mal configurato svuotava la lista."""
    sent = bounced = 0
    for contact in contacts:
        # La riga va creata PRIMA di inviare: il pixel di tracking apertura
        # (Step 8) dentro l'html porta l'id di questa riga, quindi deve
        # esistere gia' quando l'email parte.
        send_id = contact_repo.create_placeholder(contact["id"], post["id"], now)
        # Committata PRIMA dell'invio: se il run muore dopo che l'email e' partita,
        # la riga 'queued' resta e il contatto non viene ripescato (meglio uno
        # stato 'queued' orfano che una seconda email).
        checkpoint(contact_repo.conn)
        contact_html = _finalize_html(html, contact["id"], send_id)
        try:
            if not config.DRY_RUN:
                client.send(
                    contact["email"], subject, contact_html, post["nl_smtp_sender"], post["nl_smtp_username"],
                    unsubscribe_url=unsubscribe_url(contact["id"]),
                )
        except RecipientRefused as e:
            contact_repo.mark_send_bounced(send_id, contact["id"], now, str(e), permanent=e.permanent)
            checkpoint(contact_repo.conn)
            bounced += 1
            continue
        except Exception:  # noqa: BLE001 — errore dell'account SMTP, non del contatto
            contact_repo.delete_placeholder(send_id)
            checkpoint(contact_repo.conn)
            log.exception(
                "newsletter_send: post %s - errore SMTP account (host %s), batch interrotto, nessun contatto "
                "marcato bounced: si riprova al prossimo giro",
                post["id"], post["nl_smtp_host"],
            )
            break
        contact_repo.mark_sent(send_id, now)
        checkpoint(contact_repo.conn)
        sent += 1
    return sent, bounced


def _complete(post, channels, entry, post_repo, contact_repo, now) -> None:
    """Nessun contatto da servire: invio esaurito (o 0 destinatari fin dal primo
    giro). Il canale riceve completed_at e il post non viene piu' selezionato."""
    if entry.already_published:
        log.info("newsletter_send: post %s - invio completato", post["id"])
    else:
        log.warning(
            "newsletter_send: post %s - 0 destinatari (user %s, tag_id %s): tag senza contatti attivi "
            "o tutti soppressi - canale newsletter chiuso con 0 invii",
            post["id"], post["user_id"], entry.newsletter_tag_id(),
        )
    entry.data["completed_at"] = now
    _save_progress(post, channels, entry, post_repo, contact_repo)


def _save_progress(post, channels, entry, post_repo, contact_repo) -> bool:
    """Id sintetico al primo giro, stats aggiornate, published=1 quando tutti i
    canali accesi hanno un id. True se il post e' stato appena marcato pubblicato."""
    first_batch = not entry.already_published
    if first_batch:
        entry.set_result(f"smtp-{post['id']}", None)

    channels_dict = channels.to_dict()
    channels_dict["newsletter"]["stats"] = contact_repo.stats_for_post(post["id"])
    post_repo.save_channels(post["id"], channels_dict)

    if first_batch and channels.all_on_published():
        post_repo.mark_published(post["id"])
        return True
    return False


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
