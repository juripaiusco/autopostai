"""Unit test: il worker non pubblica mai un post senza testo.

Regressione (M3 revisione 2026-10-07): se l'LLM rispondeva vuoto, il testo vuoto
veniva salvato in ai_content e pubblicato su tutti i canali (Facebook/Instagram
uscivano con le sole immagini). Stesso esito con un post senza prompt.
Ora il canale va in errore, il post resta non pubblicato e nulla va in cache.
Niente DB, niente rete, niente LLM.
"""

import pytest

import main
from publisher import config
from publisher.content import ContentService, EmptyContentError
from publisher.integrations import openai_text
from publisher.publishing.base import PublishResult
from publisher.tasks import posts_send


class FakeConn:
    def get_execution_options(self):
        return {}


class FakePostRepo:
    def __init__(self):
        self.conn = FakeConn()
        self.saved_content = []
        self.saved_channels = []
        self.published = []

    def save_ai_content(self, post_id, content):
        self.saved_content.append(content)

    def save_channels(self, post_id, channels):
        self.saved_channels.append({key: dict(value) for key, value in channels.items()})

    def mark_published(self, post_id):
        self.published.append(post_id)

    def channels_of(self, post_id):
        return None


class FakeTokenRepo:
    def __init__(self):
        self.logged = []

    def is_over_limit(self, user_id):
        return False

    def log(self, user_id, ref_type, reference_id, tokens, now):
        self.logged.append(tokens)


class FakePushRepo:
    def user_has_subscription(self, user_id):
        return False


class FakePublisher:
    published = []

    def __init__(self, post, url_resolver=None):
        self.post = post

    def build_prompt(self):
        return self.post.get("ai_prompt_post") or ""

    def publish(self, content):
        FakePublisher.published.append(content)
        return PublishResult("remote-1", "https://example.test/1")


def _post(**overrides):
    post = {
        "id": 11, "user_id": 2, "created_by_user_id": 2, "title": "t",
        "ai_prompt_post": "Scrivi un post sulla pizza", "ai_content": None,
        "img": None, "img_ai_check_on": "0", "openai_api_key": "sk-fake",
        "channels": {"facebook": {"on": True}},
    }
    post.update(overrides)
    return post


@pytest.fixture
def llm(monkeypatch):
    """LLM finto: risponde con il testo impostato in llm.reply."""
    class Llm:
        reply = ""
        calls = 0

    def fake_generate(**kwargs):
        Llm.calls += 1
        return Llm.reply, 42

    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(openai_text, "generate", fake_generate)
    FakePublisher.published = []
    return Llm


def _service():
    repo, tokens = FakePostRepo(), FakeTokenRepo()
    service = ContentService(repo, tokens)
    return service, repo, tokens


@pytest.mark.parametrize("reply", ["", "   \n  "])
def test_empty_llm_reply_raises_and_is_not_cached(llm, reply):
    llm.reply = reply
    post = _post()
    service, repo, tokens = _service()

    with pytest.raises(EmptyContentError):
        service.resolve(post, FakePublisher(post), lambda *a: "")

    assert repo.saved_content == []
    assert post["ai_content"] is None
    assert tokens.logged == [42]  # la chiamata e' avvenuta: i token si contano comunque


def test_missing_prompt_raises_without_calling_the_llm(llm):
    post = _post(ai_prompt_post="  ")
    service, repo, _ = _service()

    with pytest.raises(EmptyContentError):
        service.resolve(post, FakePublisher(post), lambda *a: "")

    assert llm.calls == 0
    assert repo.saved_content == []


def test_whitespace_ai_content_is_regenerated(llm):
    llm.reply = "Testo vero"
    post = _post(ai_content=" \n ")
    service, repo, _ = _service()

    assert service.resolve(post, FakePublisher(post), lambda *a: "") == "Testo vero"
    assert repo.saved_content == ["Testo vero"]


def test_empty_reply_puts_channel_in_error_and_publishes_nothing(llm, monkeypatch):
    llm.reply = ""
    post = _post()
    repo, tokens = FakePostRepo(), FakeTokenRepo()
    monkeypatch.setitem(posts_send.PUBLISHERS, "facebook", FakePublisher)

    posts_send._send_one(post, repo, FakePushRepo(), tokens, ContentService(repo, tokens), "2026-11-03 10:00:00")

    saved = repo.saved_channels[-1]["facebook"]
    assert FakePublisher.published == []
    assert repo.published == []
    assert saved["attempts"] == 1
    assert "testo vuoto" in saved["error"]
    assert "id" not in saved


def test_preview_endpoint_rejects_empty_reply(llm):
    llm.reply = "  "

    response = main.generate_text(main.GenerateTextRequest(api_key="sk-fake", ai_prompt_post="Scrivi"))

    assert response.status_code == 422
    assert b"testo vuoto" in response.body
