"""Il token Meta e' globale (da env), non piu' per-account (settings.meta_token)."""

import inspect

from publisher import config
from publisher.db import repositories
from publisher.publishing.facebook import FacebookPublisher


def test_publisher_uses_global_env_token_not_post_column(monkeypatch):
    captured = {}

    class FakeMeta:
        def __init__(self, page_id, user_access_token):
            captured["page_id"] = page_id
            captured["token"] = user_access_token

        def fb_publish(self, content, image_urls):
            return "fb-id", "http://fb/post"

    monkeypatch.setattr("publisher.publishing.facebook.Meta", FakeMeta)
    monkeypatch.setattr(config, "META_USER_ACCESS_TOKEN", "GLOBAL-TOKEN-123")

    # Il post NON contiene piu' 'meta_token': solo meta_page_id.
    post = {"id": 1, "meta_page_id": "PAGE-42", "img": None}
    result = FacebookPublisher(post).publish("ciao")

    assert result.remote_id == "fb-id"
    assert captured["page_id"] == "PAGE-42"
    assert captured["token"] == "GLOBAL-TOKEN-123"


def test_repository_queries_no_longer_select_meta_token():
    # Sorgente dei repository: nessuna SELECT deve piu' referenziare la colonna droppata.
    source = inspect.getsource(repositories)
    assert "meta_token" not in source
