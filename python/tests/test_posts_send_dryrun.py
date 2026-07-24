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


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_posts_send_abandons_post_when_token_limit_exceeded():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()

        # Account a quota esaurita: limite basso + un token_log del mese che lo supera.
        conn.execute(text("UPDATE users SET tokens_limit = 10 WHERE id = :uid"), {"uid": user_id})
        conn.execute(
            text(
                """
                INSERT INTO token_logs (user_id, type, reference_id, tokens_used, created_at, updated_at)
                VALUES (:uid, 'post', 0, 999, NOW(), NOW())
                """
            ),
            {"uid": user_id},
        )

        channels = {
            "facebook": {"on": True, "comments_enabled": False, "auto_reply_enabled": False},
            "instagram": {"on": False},
            "linkedin": {"on": False},
            "wordpress": {"on": False},
            "newsletter": {"on": False},
        }
        # ai_content VUOTO -> richiede generazione -> il guard deve scattare.
        post_id = conn.execute(
            text(
                """
                INSERT INTO posts
                    (user_id, created_by_user_id, title, ai_content, img, channels,
                     preview, published, published_at, task_complete, created_at, updated_at)
                VALUES
                    (:uid, :uid, 'Test over-limit', NULL, NULL, :channels,
                     0, 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE), 0, NOW(), NOW())
                """
            ),
            {"uid": user_id, "channels": json.dumps(channels)},
        ).lastrowid

        posts_send.run(conn)

        row = conn.execute(
            text("SELECT channels, published, task_complete FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        saved = json.loads(row["channels"])

        # Abbandonato: published=1 + task_complete=1, nessun id remoto sui canali.
        assert str(row["published"]) == "1"
        assert str(row["task_complete"]) == "1"
        assert "id" not in saved["facebook"], "il post non doveva essere pubblicato su alcun canale"

        # Nessuna notifica accodata per un post abbandonato.
        notif = conn.execute(
            text("SELECT id FROM push_notifications WHERE user_id = :uid AND kind = 'post_published'"),
            {"uid": user_id},
        ).first()
        assert notif is None
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_posts_send_does_not_block_when_content_already_generated():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()

        # Stesso account over-limit, ma il post ha gia' ai_content: nessun token
        # da spendere -> nessun blocco, si pubblica normalmente (in DRY_RUN).
        conn.execute(text("UPDATE users SET tokens_limit = 10 WHERE id = :uid"), {"uid": user_id})
        conn.execute(
            text(
                """
                INSERT INTO token_logs (user_id, type, reference_id, tokens_used, created_at, updated_at)
                VALUES (:uid, 'post', 0, 999, NOW(), NOW())
                """
            ),
            {"uid": user_id},
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
                     preview, published, published_at, task_complete, created_at, updated_at)
                VALUES
                    (:uid, :uid, 'Test cached', 'contenuto gia pronto', NULL, :channels,
                     0, 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE), 0, NOW(), NOW())
                """
            ),
            {"uid": user_id, "channels": json.dumps(channels)},
        ).lastrowid

        posts_send.run(conn)

        row = conn.execute(
            text("SELECT channels, published FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        saved = json.loads(row["channels"])

        assert str(row["published"]) == "1"
        assert saved["facebook"]["id"].startswith("dry-facebook-"), "doveva pubblicare (nessun blocco token)"
    finally:
        trans.rollback()
        conn.close()
