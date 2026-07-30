"""Unit test di _finalize_html: link di disiscrizione (Step 7) + pixel di
tracking apertura (Step 8) iniettati nell'html per-contatto prima dell'invio."""

from publisher import config
from publisher.tasks.newsletter_send import _finalize_html


def test_finalize_html_replaces_the_unsubscribe_token_when_present(monkeypatch):
    monkeypatch.setattr(config, "APP_URL", "http://localhost")
    html = "<html><body>Ciao [unsubscribe] mondo</body></html>"

    result = _finalize_html(html, contact_id=7, send_id=99)

    assert "[unsubscribe]" not in result
    assert "/disiscrivi/7?signature=" in result


def test_finalize_html_appends_a_default_unsubscribe_footer_when_token_missing(monkeypatch):
    monkeypatch.setattr(config, "APP_URL", "http://localhost")
    html = "<html><body>Nessun token qui</body></html>"

    result = _finalize_html(html, contact_id=7, send_id=99)

    assert "/disiscrivi/7?signature=" in result
    assert "Disiscriviti" in result


def test_finalize_html_always_appends_the_tracking_pixel(monkeypatch):
    monkeypatch.setattr(config, "APP_URL", "http://localhost")
    html = "<html><body>Contenuto</body></html>"

    result = _finalize_html(html, contact_id=7, send_id=99)

    assert '<img src="http://localhost/pixel/99.gif" width="1" height="1"' in result


def test_finalize_html_pixel_uses_the_send_id_not_the_contact_id(monkeypatch):
    monkeypatch.setattr(config, "APP_URL", "http://localhost")

    result = _finalize_html("<html></html>", contact_id=7, send_id=555)

    assert "/pixel/555.gif" in result
    assert "/pixel/7.gif" not in result
