"""Task: chiude i post e gestisce il backoff del polling commenti.

Porting di `task/task_complete.py` (v1), adattato allo split v2 comments_enabled/
auto_reply_enabled/reply_n. Un canale social e' "completo" (non serve piu'
monitorarlo) quando: spento, comments_enabled=false, nessun cap configurato, o il
conteggio commenti ha raggiunto il cap (vedi Channels.social_monitoring_complete).
WordPress non ha un concetto di monitoraggio commenti: sempre completo. Newsletter
via Brevo resta aperta finche' non si recupera lo shareLink della campagna.

Il post e' task_complete quando OGNI canale acceso e' completo, oppure quando
scattano le due valvole di sicurezza ereditate da v1: 14 giorni dalla
pubblicazione, o limite di token mensile dell'account superato.

Adattamento rispetto a v1: la finestra dei 14 giorni qui si misura da
`published_at` a ORA (non da `on_hold_until - published_at`, che in v1 andava in
errore al primissimo controllo perche' on_hold_until e' NULL finche' non scatta
il primo backoff).
"""

from __future__ import annotations

import logging
from datetime import datetime, timedelta
from decimal import Decimal

from sqlalchemy.engine import Connection

from publisher import config
from publisher.db.repositories import PostRepository, TokenLogRepository
from publisher.domain.channels import CHANNEL_KEYS, Channels
from publisher.integrations.brevo import Brevo

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:
    now_dt = datetime.now(config.LOCAL_TIMEZONE)
    now = now_dt.strftime("%Y-%m-%d %H:%M:%S")

    post_repo = PostRepository(conn)
    token_repo = TokenLogRepository(conn)

    post = post_repo.due_for_comment_monitoring(now)
    if post is None:
        log.debug("task_complete: nessun post da valutare")
        return

    channels = Channels.parse(post["channels"])
    counts = {
        "facebook": post.get("facebook_comments_count") or 0,
        "instagram": post.get("instagram_comments_count") or 0,
        "linkedin": post.get("linkedin_comments_count") or 0,
    }

    complete = _all_channels_complete(channels, counts, post)

    if not complete and _elapsed_days(post["published_at"], now_dt) >= config.TASK_COMPLETE_MAX_WAIT_DAYS:
        complete = True
    if not complete and _token_limit_exceeded(token_repo, post["user_id"]):
        complete = True

    post_repo.save_channels(post["id"], channels.to_dict())

    if complete:
        post_repo.set_task_complete(post["id"])
        log.info("task_complete: post %s completato", post["id"])
    else:
        max_backoff = timedelta(minutes=config.TASK_COMPLETE_MAX_BACKOFF_MINUTES)
        next_wait = min(timedelta(minutes=2 ** (post["check_attempts"] or 0)), max_backoff)
        post_repo.set_hold(post["id"], (now_dt + next_wait).strftime("%Y-%m-%d %H:%M:%S"))
        log.debug("task_complete: post %s in attesa, prossimo controllo tra %s", post["id"], next_wait)


def _all_channels_complete(channels: Channels, counts: dict, post: dict) -> bool:
    for key in CHANNEL_KEYS:
        entry = channels.entry(key)
        if entry is None:
            continue

        if key in ("facebook", "instagram", "linkedin"):
            if not entry.social_monitoring_complete(counts.get(key, 0)):
                return False
        elif key == "wordpress":
            continue  # nessun commento da monitorare su WordPress
        elif key == "newsletter":
            if not _newsletter_complete(entry, post):
                return False

    return True


def _newsletter_complete(entry, post: dict) -> bool:
    if not entry.is_on:
        return True
    if entry.data.get("url"):
        return True
    if not post.get("nl_brevo_api"):
        # Mailchimp ha gia' l'archive_url al momento della pubblicazione; solo
        # Brevo richiede questo secondo giro per recuperare lo shareLink.
        return True

    try:
        url = Brevo(post["nl_brevo_api"]).share_link(entry.data.get("id"))
    except Exception:  # noqa: BLE001
        log.exception("task_complete: recupero shareLink Brevo fallito per il post %s", post["id"])
        return False

    if url:
        entry.data["url"] = url
        return True
    return False


def _elapsed_days(published_at, now_dt: datetime) -> int:
    if published_at is None:
        return 0
    if published_at.tzinfo is None:
        published_at = config.LOCAL_TIMEZONE.localize(published_at)
    return (now_dt - published_at).days


def _token_limit_exceeded(token_repo: TokenLogRepository, user_id: int) -> bool:
    usage = token_repo.usage_this_month(user_id)
    if not usage:
        return False
    return Decimal(usage["tokens_used_total"] or 0) >= Decimal(usage["tokens_limit"] or 0)
