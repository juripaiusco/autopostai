"""Unit test del link di disiscrizione (Step 7): solo HMAC/URL.

L'iniezione nell'html (insieme al pixel di tracking, Step 8) e' in
test_newsletter_html_finalization.py.
"""

import hashlib
import hmac

from publisher import config
from publisher.integrations.unsubscribe import unsubscribe_url


def test_unsubscribe_url_matches_laravel_signed_route_algorithm(monkeypatch):
    # Firma attesa generata da un vero URL::signedRoute('newsletter.unsubscribe',
    # ['contact' => 123], null, false) con questa chiave — osservata, non
    # ricalcolata a mano.
    monkeypatch.setattr(config, "APP_URL", "http://localhost")
    monkeypatch.setattr(config, "APP_KEY", "base64:dRZeddneXaE1p+iJ2G2FAiyNLCx+x7/bVi+NtEyV3Is=")

    url = unsubscribe_url(123)

    assert url.startswith("http://localhost/disiscrivi/123?signature=")
    signature = url.split("signature=", 1)[1]
    assert signature == "1873cf975fa02e65a7925730b59038cfd8452506b8e9e7b3dd4e441705d42129"


def test_signature_covers_only_the_path(monkeypatch):
    """Firma relativa: stesso valore per http/https e qualunque host (proxy)."""
    monkeypatch.setattr(config, "APP_KEY", "base64:whatever==")
    expected = hmac.new(b"base64:whatever==", b"/disiscrivi/42", hashlib.sha256).hexdigest()

    for app_url in ("https://faper3.it", "http://beta.faper3.it/"):
        monkeypatch.setattr(config, "APP_URL", app_url)
        assert unsubscribe_url(42) == f"{app_url.rstrip('/')}/disiscrivi/42?signature={expected}"
