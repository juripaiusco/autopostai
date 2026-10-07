"""La coda delle risposte automatiche non si blocca sui commenti solo tracciati.

Regressione: due_for_reply prendeva i primi 20 commenti senza risposta e
reply_send saltava quelli con auto_reply_enabled spento sul canale, senza
toglierli dalla coda. Con 20 commenti "commenti si', risposta no" in testa
(opzione normale in v2) il worker non arrivava piu' a nessun altro commento:
risposte automatiche ferme per tutti gli account.

DRY_RUN contro il DB di sviluppo, dentro una transazione annullata.
"""

import json
import uuid

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.db.repositories import CommentRepository
from publisher.tasks import reply_send

pytestmark = pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")


def _channels(facebook: dict) -> str:
    return json.dumps(
        {
            "facebook": {"id": f"dry-{uuid.uuid4()}", "url": "https://facebook.test/post", **facebook},
            "instagram": {"on": False},
            "linkedin": {"on": False},
            "wordpress": {"on": False},
            "newsletter": {"on": False},
        }
    )


def _insert_post(conn, user_id: int, channels: str) -> int:
    return conn.execute(
        text(
            """
            INSERT INTO posts
                (user_id, created_by_user_id, title, ai_content, img, channels,
                 preview, published, published_at, task_complete, created_at, updated_at)
            VALUES
                (:uid, :uid, 'Test coda risposte', 'contenuto', NULL, :channels,
                 0, 1, NOW(), 0, NOW(), NOW())
            """
        ),
        {"uid": user_id, "channels": channels},
    ).lastrowid


def _insert_comment(conn, post_id: int) -> int:
    return conn.execute(
        text(
            """
            INSERT INTO comments
                (post_id, channel, from_id, from_name, message_id, message, message_created_time, created_at, updated_at)
            VALUES
                (:pid, 'facebook', 'u1', 'Mario', :mid, 'commento', NOW(), NOW(), NOW())
            """
        ),
        {"pid": post_id, "mid": f"dry-{uuid.uuid4()}"},
    ).lastrowid


@pytest.fixture
def conn():
    connection = get_engine().connect()
    trans = connection.begin()
    try:
        # Coda pulita: i commenti gia' presenti nel DB dev non devono entrare nel batch.
        connection.execute(text("UPDATE comments SET reply_failed_at = NOW() WHERE reply IS NULL"))
        yield connection
    finally:
        trans.rollback()
        connection.close()


def _user_id(conn) -> int:
    user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()
    assert user_id is not None, "serve almeno un account con settings nel DB dev"
    return user_id


def test_tracked_only_comments_do_not_starve_the_reply_queue(conn):
    user_id = _user_id(conn)

    tracked_post = _insert_post(
        conn, user_id, _channels({"on": True, "comments_enabled": True, "auto_reply_enabled": False})
    )
    tracked = [_insert_comment(conn, tracked_post) for _ in range(config.REPLY_SEND_BATCH_SIZE + 5)]

    replying_post = _insert_post(
        conn, user_id, _channels({"on": True, "comments_enabled": True, "auto_reply_enabled": True})
    )
    waiting = _insert_comment(conn, replying_post)  # id piu' alto: ultimo in coda

    queue = [c["id"] for c in CommentRepository(conn).due_for_reply(limit=config.REPLY_SEND_BATCH_SIZE)]

    assert queue == [waiting]
    assert not set(queue) & set(tracked)

    reply_send.run(conn)

    reply = conn.execute(text("SELECT reply FROM comments WHERE id = :id"), {"id": waiting}).scalar()
    assert reply is not None, "il commento con auto-risposta accesa non ha ricevuto risposta"

    answered = conn.execute(
        text("SELECT COUNT(*) FROM comments WHERE post_id = :pid AND reply IS NOT NULL"), {"pid": tracked_post}
    ).scalar()
    assert answered == 0, "un commento solo tracciato ha ricevuto una risposta automatica"


def test_post_without_the_auto_reply_key_never_enters_the_queue(conn):
    """Canale in forma v1 (nessuna chiave auto_reply_enabled): fuori dalla coda,
    non un errore SQL."""
    user_id = _user_id(conn)
    legacy_post = _insert_post(conn, user_id, _channels({"on": "1", "reply_on": "1", "reply_n": "5"}))
    _insert_comment(conn, legacy_post)

    assert CommentRepository(conn).due_for_reply(limit=config.REPLY_SEND_BATCH_SIZE) == []
