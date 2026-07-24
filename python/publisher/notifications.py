"""Accodamento della notifica funzionale "post inviato".

Il worker NON firma ne' invia WebPush (la firma VAPID vive nel pacchetto webpush
lato Laravel): inserisce solo una riga pending in `push_notifications` con
kind='post_published'. Il comando schedulato Laravel `notifications:send-pending`
la preleva e la consegna tramite la classe PostPublishedAlert (solo WebPush, NON
in campanella). Notifichiamo solo se l'autore ha una subscription push attiva,
per non accumulare righe inutili.
"""

from __future__ import annotations

from publisher import config
from publisher.db.repositories import PushNotificationRepository


def notify_post_published(push_repo: PushNotificationRepository, post: dict, now: str) -> bool:
    user_id = post["created_by_user_id"]
    if not push_repo.user_has_subscription(user_id):
        return False

    push_repo.enqueue_post_published(
        user_id=user_id,
        title=post["title"],
        url=f"{config.APP_URL}/posts/show/{post['id']}",
        now=now,
    )
    return True
