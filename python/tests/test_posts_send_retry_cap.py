"""Unit test: un canale che fallisce viene ritentato un numero limitato di volte.

Regressione: un canale fallito restava senza id e posts_send lo ritentava ogni
minuto per sempre, ripartendo ogni volta da zero (nuova campagna Mailchimp/Brevo,
nuovo upload di immagini su WordPress) e senza che l'utente vedesse l'errore.
Peggio su Instagram: la lettura del permalink DOPO la pubblicazione poteva
fallire, il canale risultava fallito e al giro dopo il post usciva due volte.
Niente DB, niente rete, niente LLM.
"""

import pytest
import requests

from publisher import config
from publisher.domain.channels import Channels
from publisher.integrations import http
from publisher.integrations.meta import Meta
from publisher.publishing.base import PublishResult
from publisher.tasks import posts_send


class FakeConn:
    def get_execution_options(self):
        return {}


class FakePostRepo:
    def __init__(self):
        self.conn = FakeConn()
        self.saved = []
        self.published = []

    def save_channels(self, post_id, channels):
        self.saved.append({key: dict(value) for key, value in channels.items()})

    def mark_published(self, post_id):
        self.published.append(post_id)

    def channels_of(self, post_id):
        return None


class FakePushRepo:
    def user_has_subscription(self, user_id):
        return False


class FakeTokenRepo:
    def is_over_limit(self, user_id):
        return False


class FakeContentService:
    def resolve(self, post, publisher, resolver):
        return "contenuto"


def _publisher(outcomes, calls):
    """Publisher finto: ad ogni chiamata consuma il prossimo esito della lista
    (un'eccezione da sollevare oppure l'id remoto da restituire)."""

    class FakePublisher:
        def __init__(self, post, url_resolver=None):
            pass

        def publish(self, content):
            calls.append(content)
            outcome = outcomes.pop(0)
            if isinstance(outcome, Exception):
                raise outcome
            return PublishResult(outcome, f"https://example.test/{outcome}")

    return FakePublisher


def _post(channels):
    return {"id": 11, "user_id": 2, "created_by_user_id": 2, "title": "t", "ai_content": "x", "channels": channels}


def _run(monkeypatch, post, outcomes):
    calls = []
    repo = FakePostRepo()
    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setitem(posts_send.PUBLISHERS, "facebook", _publisher(outcomes, calls))
    posts_send._send_one(post, repo, FakePushRepo(), FakeTokenRepo(), FakeContentService(), "2026-10-07 10:00:00")
    return repo, calls


def test_failed_channel_stores_attempt_count_and_error(monkeypatch):
    post = _post({"facebook": {"on": True}})

    repo, calls = _run(monkeypatch, post, [RuntimeError("pagina non collegata")])

    saved = repo.saved[-1]["facebook"]
    assert len(calls) == 1
    assert saved["attempts"] == 1
    assert "pagina non collegata" in saved["error"]
    assert "id" not in saved
    assert repo.published == []


def test_channel_stops_after_max_attempts(monkeypatch):
    channels = {"facebook": {"on": True}}
    total_calls = 0

    # Un giro del worker per tentativo: il JSON salvato e' l'input del giro dopo.
    for _ in range(config.PUBLISH_MAX_ATTEMPTS + 2):
        repo, calls = _run(monkeypatch, _post(channels), [RuntimeError("no")])
        total_calls += len(calls)
        channels = repo.saved[-1] if repo.saved else channels

    assert total_calls == config.PUBLISH_MAX_ATTEMPTS
    assert channels["facebook"]["attempts"] == config.PUBLISH_MAX_ATTEMPTS
    assert Channels.parse(channels).publishable() == []
    assert Channels.parse(channels).all_on_published() is False  # mai "pubblicato" per finta


def test_read_timeout_stops_the_channel_at_once(monkeypatch):
    """Richiesta partita, risposta mai arrivata: il provider puo' averla
    eseguita. Un altro tentativo automatico manderebbe due volte la newsletter."""
    post = _post({"facebook": {"on": True}})

    repo, _ = _run(monkeypatch, post, [requests.exceptions.ReadTimeout("read timed out")])

    saved = repo.saved[-1]["facebook"]
    assert saved["attempts"] == config.PUBLISH_MAX_ATTEMPTS
    assert saved["error"].startswith("Esito incerto")
    assert Channels.parse(repo.saved[-1]).publishable() == []


def test_connect_timeout_is_retried_normally(monkeypatch):
    post = _post({"facebook": {"on": True}})

    repo, _ = _run(monkeypatch, post, [requests.exceptions.ConnectTimeout("connect timed out")])

    assert repo.saved[-1]["facebook"]["attempts"] == 1


def test_success_after_a_failure_clears_the_error(monkeypatch):
    post = _post({"facebook": {"on": True, "attempts": 2, "error": "vecchio errore"}})

    repo, calls = _run(monkeypatch, post, ["fb-123"])

    saved = repo.saved[-1]["facebook"]
    assert len(calls) == 1
    assert saved["id"] == "fb-123"
    assert "attempts" not in saved and "error" not in saved
    assert repo.published == [11]


def test_a_failed_channel_does_not_block_the_others(monkeypatch):
    calls_ok = []
    monkeypatch.setitem(posts_send.PUBLISHERS, "linkedin", _publisher(["li-9"], calls_ok))
    post = _post({"facebook": {"on": True}, "linkedin": {"on": True}})

    repo, _ = _run(monkeypatch, post, [RuntimeError("no")])

    assert repo.saved[-1]["linkedin"]["id"] == "li-9"
    assert repo.saved[-1]["facebook"]["attempts"] == 1
    assert repo.published == []  # post parziale


def test_http_error_message_includes_the_provider_explanation():
    response = requests.Response()
    response.status_code = 400
    response._content = b'{"error":{"message":"Invalid OAuth access token"}}'
    error = requests.exceptions.HTTPError("400 Client Error", response=response)

    message = posts_send._error_message(error)

    assert "Invalid OAuth access token" in message
    assert len(message) <= 500


@pytest.mark.parametrize("failure", [requests.exceptions.ReadTimeout("slow"), ValueError("non json")])
def test_instagram_permalink_failure_does_not_fail_the_publication(monkeypatch, failure):
    """Il post e' gia' online quando si legge il permalink: se quella lettura
    fallisce il canale deve comunque risultare pubblicato (url assente)."""

    def ok(body):
        resp = requests.Response()
        resp.status_code = 200
        resp._content = __import__("json").dumps(body).encode()
        return resp

    def fake_post(url, **kwargs):
        return ok({"id": "container-1"} if url.endswith("/media") else {"id": "ig-post-1"})

    def fake_get(url, **kwargs):
        if url.endswith("/me/accounts"):
            return ok({"data": [{"id": "page-1", "access_token": "page-token"}]})
        if url.endswith("/page-1"):
            return ok({"instagram_business_account": {"id": "ig-account"}})
        raise failure  # lettura del permalink

    monkeypatch.setattr(http, "post", fake_post)
    monkeypatch.setattr(http, "get", fake_get)

    post_id, url = Meta("page-1", "user-token").ig_publish("testo", ["https://example.test/a.jpg"])

    assert post_id == "ig-post-1"
    assert url is None
