"""Unit test: delete remota su un post gia' rimosso a mano = rimosso.

Regressione: una 404/410 (o per Facebook il 400 con error_subcode 33) faceva
sollevare o restituire None -> id_del != id -> posts_delete ripescava il post ogni
minuto, per sempre. Nessuna chiamata di rete: requests.delete e' sostituito.
"""

import pytest
import requests

from publisher.integrations.linkedin import LinkedIn
from publisher.integrations.mailchimp import Mailchimp
from publisher.integrations.meta import Meta
from publisher.integrations.wordpress import WordPress


class FakeResponse:
    def __init__(self, status_code, body=None):
        self.status_code = status_code
        self._body = body

    def json(self):
        if self._body is None:
            raise ValueError("no json")
        return self._body

    def raise_for_status(self):
        if self.status_code >= 400:
            raise requests.HTTPError(f"HTTP {self.status_code}")


@pytest.fixture
def respond(monkeypatch):
    """respond(status, body) -> la prossima requests.delete restituisce quella risposta."""
    def _set(status_code, body=None):
        monkeypatch.setattr(requests, "delete", lambda *a, **kw: FakeResponse(status_code, body))
    return _set


@pytest.fixture
def meta(monkeypatch):
    monkeypatch.setattr(Meta, "page_access_token", lambda self: "page-token")
    return Meta("page-1", "user-token")


def _clients():
    return {
        "wordpress": WordPress("https://wp.example", "user", "pass"),
        "linkedin": LinkedIn(token="t", company_id="c"),
        "mailchimp": Mailchimp("key-us1", "us1"),
    }


# --- Facebook ---

def test_fb_delete_ok(respond, meta):
    respond(200, {"success": True})
    assert meta.fb_delete("123_456") == "123_456"


@pytest.mark.parametrize("status,body", [
    (400, {"error": {"code": 100, "error_subcode": 33, "message": "Object does not exist"}}),
    (404, None),
])
def test_fb_delete_already_removed(respond, meta, status, body):
    respond(status, body)
    assert meta.fb_delete("123_456") == "123_456"


def test_fb_delete_other_errors_still_raise(respond, meta):
    respond(400, {"error": {"code": 190, "message": "Invalid OAuth access token"}})
    with pytest.raises(requests.HTTPError):
        meta.fb_delete("123_456")


# --- WordPress / LinkedIn / Mailchimp ---

@pytest.mark.parametrize("key,status", [
    ("wordpress", 404), ("wordpress", 410),
    ("linkedin", 404),
    ("mailchimp", 404),
])
def test_delete_already_removed_returns_id(respond, key, status):
    respond(status, {"code": "gone"})
    assert _clients()[key].delete("42") == "42"


@pytest.mark.parametrize("key", ["wordpress", "linkedin", "mailchimp"])
def test_delete_auth_errors_raise_instead_of_silent_none(respond, key):
    respond(401, {"message": "unauthorized"})
    with pytest.raises(requests.HTTPError):
        _clients()[key].delete("42")


@pytest.mark.parametrize("key,status,body", [
    ("wordpress", 200, {"id": 42}),
    ("linkedin", 204, None),
    ("mailchimp", 204, None),
])
def test_delete_ok(respond, key, status, body):
    respond(status, body)
    assert _clients()[key].delete("42") in ("42", 42)
