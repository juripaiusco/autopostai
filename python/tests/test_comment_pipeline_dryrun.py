"""End-to-end DRY_RUN di comments_get -> reply_send -> task_complete.

Come test_posts_send_dryrun.py: seed minimale, transazione con rollback, nessun
residuo nel DB. Copre la pipeline dei 3 task che dipendono dai commenti.
"""

import json
import uuid

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.tasks import comments_get, reply_send, task_complete


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_comment_pipeline_fetches_replies_and_completes_the_task():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()
        assert user_id is not None, "serve almeno un account con settings nel DB dev"
        # Isola il test dalla valvola "limite token superato" (default dev = 0).
        conn.execute(text("UPDATE users SET tokens_limit = 1000000 WHERE id = :uid"), {"uid": user_id})

        # Post gia' pubblicato su facebook, con un cap di 1 risposta: basta un
        # commento fetchato+risposto perche' il canale (e quindi il post) risulti completo.
        channels = {
            "facebook": {
                "on": True,
                "comments_enabled": True,
                "auto_reply_enabled": True,
                "reply_n": 1,
                "id": f"dry-existing-{uuid.uuid4()}",
                "url": "https://facebook.test/post",
            },
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
                    (:uid, :uid, 'Test pipeline commenti', 'contenuto', NULL, :channels,
                     0, 1, NOW(), 0, NOW(), NOW())
                """
            ),
            {"uid": user_id, "channels": json.dumps(channels)},
        ).lastrowid

        # --- comments_get: fetcha (in DRY_RUN, simulato) un commento facebook ---
        comments_get.run(conn)

        comment = conn.execute(
            text("SELECT id, reply FROM comments WHERE post_id = :pid AND channel = 'facebook'"),
            {"pid": post_id},
        ).mappings().first()
        assert comment is not None, "comments_get non ha salvato il commento simulato"
        assert comment["reply"] is None

        # Rieseguire comments_get non deve duplicare (dedup su message_id).
        comments_get.run(conn)
        count = conn.execute(
            text("SELECT COUNT(*) FROM comments WHERE post_id = :pid"), {"pid": post_id}
        ).scalar()
        assert count == 1, "il fetch ripetuto ha duplicato il commento (dedup rotto)"

        # --- reply_send: genera e salva una risposta simulata ---
        reply_send.run(conn)

        replied = conn.execute(
            text("SELECT reply, reply_id FROM comments WHERE id = :id"), {"id": comment["id"]}
        ).mappings().first()
        assert replied["reply"] is not None
        assert replied["reply_id"] is not None

        # --- task_complete: cap=1 raggiunto -> il post deve chiudersi ---
        task_complete.run(conn)

        row = conn.execute(
            text("SELECT task_complete FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        assert str(row["task_complete"]) == "1", "il post non e' stato marcato task_complete nonostante il cap raggiunto"
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_task_complete_backs_off_when_cap_not_reached():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()

        # tokens_limit di default e' 0 in dev: senza alzarlo, la valvola di
        # sicurezza "limite token superato" (0 usati >= 0 di limite) chiuderebbe
        # il post subito, mascherando il comportamento che questo test verifica.
        conn.execute(text("UPDATE users SET tokens_limit = 1000000 WHERE id = :uid"), {"uid": user_id})

        channels = {
            "facebook": {
                "on": True,
                "comments_enabled": True,
                "auto_reply_enabled": True,
                "reply_n": 5,  # cap alto, nessun commento ancora fetchato -> non completo
                "id": f"dry-existing-{uuid.uuid4()}",
                "url": "https://facebook.test/post",
            },
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
                     preview, published, published_at, task_complete, check_attempts, created_at, updated_at)
                VALUES
                    (:uid, :uid, 'Test backoff', 'contenuto', NULL, :channels,
                     0, 1, NOW(), 0, 0, NOW(), NOW())
                """
            ),
            {"uid": user_id, "channels": json.dumps(channels)},
        ).lastrowid

        task_complete.run(conn)

        row = conn.execute(
            text("SELECT task_complete, check_attempts, on_hold_until FROM posts WHERE id = :id"),
            {"id": post_id},
        ).mappings().first()
        assert str(row["task_complete"]) == "0"
        assert row["check_attempts"] == 1
        assert row["on_hold_until"] is not None
    finally:
        trans.rollback()
        conn.close()
