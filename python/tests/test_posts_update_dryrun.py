"""posts_update: modifica del testo di un post gia' pubblicato, per canale.

I test *_dryrun girano contro il DB di sviluppo (transazione con rollback,
PUBLISHER_DRY_RUN=1); gli altri sono unitari, senza DB ne' chiamate esterne.
"""

import json

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.domain.channels import Channels
from publisher.tasks import posts_update


def _make_updated_post(conn, user_id: int, channels: dict) -> int:
    return conn.execute(
        text(
            """
            INSERT INTO posts
                (user_id, created_by_user_id, title, ai_content, channels, preview, published, deleted,
                 updated, published_at, created_at, updated_at)
            VALUES
                (:uid, :uid, 'Post modificato', 'Testo comune', :channels, 0, 1, '0',
                 '2', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), NOW())
            """
        ),
        {"uid": user_id, "channels": json.dumps(channels)},
    ).lastrowid


def _run_on(channels: dict) -> tuple[dict, str]:
    """Inserisce un post updated=2, esegue il task e ritorna (channels, updated)."""
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = conn.execute(text("SELECT user_id FROM settings LIMIT 1")).scalar()
        assert user_id is not None, "serve almeno un account con settings nel DB dev"
        # Il task prende un post per run: gli altri in coda nel DB dev restano fuori.
        conn.execute(text("UPDATE posts SET updated = '0' WHERE updated = '2'"))

        post_id = _make_updated_post(conn, user_id, channels)
        posts_update.run(conn)

        row = conn.execute(
            text("SELECT channels, updated FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        return json.loads(row["channels"]), str(row["updated"])
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_only_the_edited_channel_is_updated():
    channels, updated = _run_on({
        "facebook": {"on": True, "id": "123_456", "content": "Testo corretto", "update_pending": True},
        "wordpress": {"on": True, "id": "77"},
        "instagram": {"on": True, "id": "789"},
    })

    assert updated == "1"
    assert channels["facebook"]["id_update"] == "123_456"
    assert "update_pending" not in channels["facebook"]
    assert channels["facebook"]["content"] == "Testo corretto"
    assert "id_update" not in channels["wordpress"]
    assert "id_update" not in channels["instagram"]


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_v1_post_without_pending_flag_updates_wordpress_like_v1():
    channels, updated = _run_on({
        "facebook": {"on": True, "id": "123_456"},
        "wordpress": {"on": True, "id": "77"},
    })

    assert updated == "1"
    assert channels["wordpress"]["id_update"] == "77"
    assert "id_update" not in channels["facebook"]


def test_keys_to_update_skips_unpublished_and_not_updatable_channels():
    channels = Channels.parse({
        "facebook": {"on": True, "update_pending": True},  # non ancora pubblicato
        "wordpress": {"on": True, "id": "77", "update_pending": True},
        "instagram": {"on": True, "id": "789", "update_pending": True},
    })

    assert posts_update._keys_to_update(channels) == ["wordpress"]


def test_wordpress_update_keeps_the_gallery(monkeypatch):
    calls = {}

    class FakeWordPress:
        def __init__(self, **_):
            pass

        def update(self, post_id, title, body):
            calls.update(post_id=post_id, title=title, body=body)
            return post_id

    monkeypatch.setattr(posts_update, "WordPress", FakeWordPress)
    post = {"wordpress_url": "https://wp.test", "wordpress_username": "u", "wordpress_password": "p"}

    posts_update._wordpress_update(post, {"id": "77", "gallery_html": "<figure>G</figure>"}, "# Titolo\nCorpo nuovo")

    assert calls["post_id"] == "77"
    assert calls["title"] == "Titolo"
    assert calls["body"].startswith("<figure>G</figure><br>")
    assert "Corpo nuovo" in calls["body"]


def test_failed_update_is_recorded_and_not_retried(monkeypatch):
    saved = {}

    class FakeRepo:
        def __init__(self, _conn):
            pass

        def due_for_update(self):
            return {"id": 1, "user_id": 1, "ai_content": "Testo", "channels": {
                "facebook": {"on": True, "id": "123_456", "update_pending": True},
            }}

        def save_channels(self, _post_id, channels):
            saved["channels"] = channels

        def set_updated(self, _post_id, value):
            saved["updated"] = value

    def boom(*_):
        raise RuntimeError("token scaduto")

    monkeypatch.setattr(posts_update, "PostRepository", FakeRepo)
    monkeypatch.setattr(posts_update.config, "DRY_RUN", False)
    monkeypatch.setitem(posts_update._UPDATERS, "facebook", boom)

    posts_update.run(conn=None)

    fb = saved["channels"]["facebook"]
    assert "update_pending" not in fb
    assert "token scaduto" in fb["update_error"]
    assert "id_update" not in fb
    assert saved["updated"] == "1"
