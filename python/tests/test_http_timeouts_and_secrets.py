"""Unit test: chiamate HTTP sempre con timeout, token Meta mai nell'URL.

Regressioni: nessuna chiamata `requests` aveva un timeout (un'API bloccata teneva
fermo il run e, col lock tra run, anche i successivi); il token Meta viaggiava in
query string e finiva nei log tramite i messaggi di errore di requests.
Nessuna chiamata di rete.
"""

import re
from pathlib import Path

import pytest
import requests

from publisher import config
from publisher.integrations import http, openai_text
from publisher.integrations.meta import Meta

INTEGRATIONS = Path(__file__).resolve().parent.parent / "publisher" / "integrations"


class Recorder:
    def __init__(self, body=None):
        self.calls = []
        self.body = body if body is not None else {}

    def __call__(self, url, **kwargs):
        self.calls.append((url, kwargs))
        resp = requests.Response()
        resp.status_code = 200
        resp._content = __import__("json").dumps(self.body).encode()
        return resp


@pytest.mark.parametrize("method", ["get", "post", "put", "delete"])
def test_http_wrapper_adds_default_timeout(monkeypatch, method):
    rec = Recorder()
    monkeypatch.setattr(requests, method, rec)

    getattr(http, method)("https://api.example/x")
    getattr(http, method)("https://api.example/y", timeout=5)

    assert rec.calls[0][1]["timeout"] == config.HTTP_TIMEOUT
    assert rec.calls[1][1]["timeout"] == 5


def test_integrations_never_call_requests_directly():
    """Ogni nuova chiamata deve passare da integrations/http.py (timeout)."""
    offenders = [
        f"{path.name}:{n}"
        for path in INTEGRATIONS.glob("*.py")
        if path.name != "http.py"
        for n, line in enumerate(path.read_text().splitlines(), 1)
        if re.search(r"\brequests\.(get|post|put|delete|patch|request)\(", line)
    ]
    assert offenders == []


def test_meta_token_never_in_query_string(monkeypatch):
    get = Recorder({"data": [{"id": "page-1", "access_token": "PAGE-TOKEN"}], "success": True})
    delete = Recorder({"success": True})
    monkeypatch.setattr(requests, "get", get)
    monkeypatch.setattr(requests, "delete", delete)
    meta = Meta("page-1", "USER-TOKEN")

    meta.page_access_token()
    meta.instagram_account_id()
    meta.fb_get_comments("post-1")
    meta.ig_get_comments("post-1")
    meta.fb_delete("post-1")

    calls = get.calls + delete.calls
    assert len(calls) >= 5
    for url, kwargs in calls:
        assert "TOKEN" not in url
        assert "access_token" not in (kwargs.get("params") or {})
        assert kwargs["headers"]["Authorization"] in ("Bearer USER-TOKEN", "Bearer PAGE-TOKEN")
        assert kwargs["timeout"] == config.HTTP_TIMEOUT


def test_openai_client_has_bounded_timeout(monkeypatch):
    captured = {}

    class FakeOpenAI:
        def __init__(self, **kwargs):
            captured.update(kwargs)
            raise RuntimeError("stop")  # basta verificare la costruzione

    monkeypatch.setattr(openai_text, "OpenAI", FakeOpenAI)
    with pytest.raises(RuntimeError):
        openai_text.generate("sk-test", "system", "user")

    assert captured["timeout"] == config.OPENAI_TIMEOUT
    assert captured["max_retries"] == config.OPENAI_MAX_RETRIES
