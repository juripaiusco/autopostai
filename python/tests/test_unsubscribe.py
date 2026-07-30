"""Unit test del link di disiscrizione (Step 7): HMAC + iniezione nel corpo email."""

import hashlib
import hmac

from publisher import config
from publisher.integrations.unsubscribe import unsubscribe_url
from publisher.tasks.newsletter_send import _inject_unsubscribe


def test_unsubscribe_url_matches_laravel_signed_route_algorithm(monkeypatch):
    # Stessa chiave/URL usati per verificare manualmente contro un vero
    # URL::signedRoute() generato da Laravel in fase di sviluppo — la firma
    # attesa qui e' quella osservata realmente, non ricalcolata a mano.
    monkeypatch.setattr(config, "APP_URL", "http://localhost")
    monkeypatch.setattr(config, "APP_KEY", "base64:dRZeddneXaE1p+iJ2G2FAiyNLCx+x7/bVi+NtEyV3Is=")

    url = unsubscribe_url(123)

    assert url.startswith("http://localhost/disiscrivi/123?signature=")
    signature = url.split("signature=", 1)[1]
    assert signature == "06f0a4c2380e48913c550d3aecbd3b008398c06c85ba8d0b42c785f2e8f405cf"


def test_unsubscribe_url_is_a_valid_hmac_for_any_key(monkeypatch):
    monkeypatch.setattr(config, "APP_URL", "https://faper3.it")
    monkeypatch.setattr(config, "APP_KEY", "base64:whatever==")

    url = unsubscribe_url(42)
    base = "https://faper3.it/disiscrivi/42"
    expected = hmac.new(b"base64:whatever==", base.encode(), hashlib.sha256).hexdigest()

    assert url == f"{base}?signature={expected}"


def test_inject_unsubscribe_replaces_the_token_when_present():
    html = "<html><body>Ciao [unsubscribe] mondo</body></html>"
    result = _inject_unsubscribe(html, 7)

    assert "[unsubscribe]" not in result
    assert "/disiscrivi/7?signature=" in result


def test_inject_unsubscribe_appends_a_default_footer_when_token_missing():
    html = "<html><body>Nessun token qui</body></html>"
    result = _inject_unsubscribe(html, 7)

    assert result.startswith(html)
    assert "/disiscrivi/7?signature=" in result
    assert "Disiscriviti" in result
