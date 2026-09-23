"""posts_delete contro il DB di sviluppo (transazione con rollback, PUBLISHER_DRY_RUN=1).

Regressione: due_for_deletion selezionava solo published=1. Un post parziale
(uscito su alcuni canali, fallito su altri: published resta 0) eliminato
dall'utente non veniva mai rimosso dai canali su cui era gia' uscito.
"""

import json

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.tasks import posts_delete


def _make_deleted_post(conn, user_id: int, published: int, channels: dict) -> int:
    return conn.execute(
        text(
            """
            INSERT INTO posts
                (user_id, created_by_user_id, title, channels, preview, published, deleted,
                 published_at, deleted_at, created_at, updated_at)
            VALUES
                (:uid, :uid, 'Post da eliminare', :channels, 0, :published, '0',
                 DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 MINUTE), NOW(), NOW())
            """
        ),
        {"uid": user_id, "channels": json.dumps(channels), "published": published},
    ).lastrowid


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_partial_post_is_removed_from_channels_where_it_was_published():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()
        assert user_id is not None, "serve almeno un account con settings nel DB dev"

        partial = _make_deleted_post(conn, user_id, 0, {
            "facebook": {"on": True, "id": "123_456", "url": "https://facebook.com/123_456"},
            "linkedin": {"on": True},
        })
        never_published = _make_deleted_post(conn, user_id, 0, {"facebook": {"on": True}})

        due_ids = [p["id"] for p in posts_delete.PostRepository(conn).due_for_deletion("2999-01-01 00:00:00")]
        assert partial in due_ids
        assert never_published not in due_ids  # niente di remoto da togliere

        posts_delete.run(conn)

        row = conn.execute(
            text("SELECT channels, deleted FROM posts WHERE id = :id"), {"id": partial}
        ).mappings().first()
        channels = json.loads(row["channels"])
        assert channels["facebook"]["id_del"] == "123_456"
        assert str(row["deleted"]) == "1"
    finally:
        trans.rollback()
        conn.close()
