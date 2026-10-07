"""Task: pubblica i post schedulati e accoda la notifica "post inviato".

Porting ottimizzato di `task/posts_send.py` (v1). Flusso per ogni post scaduto:
  1. per ogni canale acceso e non ancora pubblicato: genera/riusa il contenuto e pubblica;
  2. salva id/url remoti nel JSON channels;
  3. se TUTTI i canali accesi hanno un id => marca il post published e accoda la notifica.

Un canale che fallisce non blocca gli altri canali/post: resta senza id, con il
numero di tentativi e l'ultimo errore salvati nel canale, e viene ritentato al
run successivo fino a config.PUBLISH_MAX_ATTEMPTS. Poi si ferma: prima veniva
ritentato ogni minuto per sempre, rifacendo ogni volta upload e campagne.
"""

from __future__ import annotations

import logging
from datetime import datetime

import requests
from sqlalchemy.engine import Connection

from publisher import config
from publisher.content import ContentService
from publisher.db.engine import checkpoint
from publisher.db.repositories import PostRepository, PushNotificationRepository, TokenLogRepository
from publisher.domain.channels import Channels
from publisher.notifications import notify_post_published
from publisher.publishing import PUBLISHERS

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")

    post_repo = PostRepository(conn)
    push_repo = PushNotificationRepository(conn)
    token_repo = TokenLogRepository(conn)
    content_service = ContentService(post_repo, token_repo)

    posts = post_repo.due_posts(now)
    log.info("posts_send: %d post da valutare", len(posts))

    for post in posts:
        _send_one(post, post_repo, push_repo, token_repo, content_service, now)


def _send_one(post, post_repo, push_repo, token_repo, content_service, now) -> None:
    # Limite token: se il post richiede generazione (nessun ai_content in cache) e
    # l'account ha esaurito la quota, il post viene abbandonato senza pubblicare
    # (fedele a v1). Se ai_content e' gia' presente non si spende nulla, quindi
    # nessun blocco: il controllo scattava solo dentro ai_generate, mai sul contenuto
    # gia' generato.
    if not post.get("ai_content") and token_repo.is_over_limit(post["user_id"]):
        post_repo.abandon_over_limit(post["id"])
        log.info("posts_send: post %s abbandonato (limite token superato)", post["id"])
        return

    channels = Channels.parse(post["channels"])
    resolver = _url_resolver(post_repo, post["user_id"])

    for entry in channels.publishable():
        publisher = PUBLISHERS[entry.key](post, url_resolver=resolver)
        log.info("posts_send: post %s canale '%s' - generazione contenuto e invio", post["id"], entry.key)
        try:
            content = content_service.resolve(post, publisher, resolver)
            result = publisher.simulate() if config.DRY_RUN else publisher.publish(content)
        except Exception as exc:  # noqa: BLE001 — un canale non deve far cadere gli altri
            log.exception("posts_send: canale '%s' fallito per il post %s", entry.key, post["id"])
            entry.record_failure(_error_message(exc), final=_outcome_unknown(exc))
            post_repo.save_channels(post["id"], channels.to_dict())
            checkpoint(post_repo.conn)
            if entry.gave_up:
                log.error(
                    "posts_send: post %s canale '%s' - fermo dopo %d tentativi, non verra' ritentato",
                    post["id"], entry.key, entry.attempts,
                )
            continue
        entry.set_result(result.remote_id, result.url, result.gallery_html)
        # Salvato e committato canale per canale: se il run si interrompe dopo
        # questa pubblicazione, il run successivo non la ripete.
        post_repo.save_channels(post["id"], channels.to_dict())
        checkpoint(post_repo.conn)
        log.info("posts_send: post %s canale '%s' - pubblicato (id=%s)", post["id"], entry.key, result.remote_id)

    # Post pubblicato solo se ogni canale acceso ha un id remoto.
    if channels.all_on_published():
        post_repo.mark_published(post["id"])
        if notify_post_published(push_repo, post, now):
            log.info("posts_send: post %s pubblicato, notifica accodata", post["id"])
        else:
            log.info("posts_send: post %s pubblicato (autore senza subscription push)", post["id"])


def _outcome_unknown(exc: Exception) -> bool:
    """La richiesta e' partita ma la risposta non e' arrivata: il provider puo'
    averla eseguita lo stesso. Ritentare da soli farebbe uscire il post (o la
    newsletter) due volte, quindi il canale si ferma subito."""
    return isinstance(exc, requests.exceptions.ReadTimeout)


def _error_message(exc: Exception) -> str:
    """Messaggio breve per la pagina del post: tipo di errore e, per le risposte
    HTTP, l'inizio del corpo (li' i provider spiegano il rifiuto)."""
    if _outcome_unknown(exc):
        return "Esito incerto: il canale non ha risposto in tempo. Controlla se il post è uscito prima di riprovare."
    message = f"{type(exc).__name__}: {exc}"
    response = getattr(exc, "response", None)
    body = getattr(response, "text", "") or ""
    if body:
        message = f"{message} — {body[:200]}"
    return message[:500]


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
