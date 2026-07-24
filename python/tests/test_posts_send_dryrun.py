"""End-to-end DRY_RUN di posts_send contro il DB di sviluppo.

Valida l'orchestrazione (query -> parsing channels -> pubblicazione simulata ->
scrittura channels -> published=1 -> notifica accodata) SENZA chiamare provider o
LLM. Tutto avviene dentro una transazione che viene fatta rollback: nessun residuo
nel DB. Richiede un DB raggiungibile (gira nel container, non nella CI pura).

Attiva la modalita' dry-run impostando PUBLISHER_DRY_RUN=1 nell'ambiente.
"""

import json
import uuid

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.tasks import posts_send


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_posts_send_dryrun_publishes_and_enqueues_notification():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()
        assert user_id is not None, "serve almeno un account con settings nel DB dev"

        # Subscription push per far accodare la notifica.
        conn.execute(
            text(
                """
                INSERT INTO push_subscriptions
                    (subscribable_type, subscribable_id, endpoint, created_at, updated_at)
                VALUES ('App\\\\Models\\\\User', :uid, :endpoint, NOW(), NOW())
                """
            ),
            {"uid": user_id, "endpoint": f"https://push.test/{uuid.uuid4()}"},
        )

        channels = {
            "facebook": {"on": True, "comments_enabled": False, "auto_reply_enabled": False},
            "instagram": {"on": False},
            "linkedin": {"on": False},
            "wordpress": {"on": False},
            "newsletter": {"on": False},
        }
        post_id = conn.execute(
            text(
                """
                INSERT INTO posts
                    (user_id, created_by_user_id, title, ai_content, img, channels,
                     preview, published, published_at, created_at, updated_at)
                VALUES
                    (:uid, :uid, 'Test DRY_RUN', 'contenuto', NULL, :channels,
                     0, 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())
                """
            ),
            {"uid": user_id, "channels": json.dumps(channels)},
        ).lastrowid

        # --- esecuzione ---
        posts_send.run(conn)

        # --- asserzioni ---
        row = conn.execute(
            text("SELECT channels, published FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        saved = json.loads(row["channels"])

        assert saved["facebook"]["id"].startswith("dry-facebook-"), saved["facebook"]
        assert saved["facebook"]["url"]
        assert str(row["published"]) == "1"

        notif = conn.execute(
            text(
                """
                SELECT kind, sent_at FROM push_notifications
                WHERE user_id = :uid AND kind = 'post_published'
                ORDER BY id DESC LIMIT 1
                """
            ),
            {"uid": user_id},
        ).mappings().first()
        assert notif is not None, "notifica 'post_published' non accodata"
        assert notif["sent_at"] is None, "la riga deve restare pending (sent_at NULL)"
    finally:
        trans.rollback()
        conn.close()
