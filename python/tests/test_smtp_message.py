"""Unit test: struttura dell'email smtp_custom (header e parti).

Gmail/Yahoo richiedono per gli invii massivi List-Unsubscribe +
List-Unsubscribe-Post (one-click RFC 8058), e un messaggio senza Date/
Message-ID o solo-html pesa sul punteggio antispam.
"""

from publisher.integrations.smtp import build_message, html_to_text

HTML = (
    "<html><head><style>p{color:red}</style></head><body>"
    "<h1>Novità &amp; sconti</h1><p>Ciao <b>Mario</b>,<br>ecco le offerte.</p>"
    '<a href="https://x.it/p">Scopri di più</a><img src="https://x.it/pixel/1.gif"></body></html>'
)
UNSUB = "https://faper3.it/disiscrivi/7?signature=abc"


def _message(unsubscribe_url=UNSUB):
    return build_message("to@example.com", "Novità", HTML, "Trattoria <news@trattoria.it>", "user", unsubscribe_url)


def test_bulk_sender_headers():
    msg = _message()

    assert msg["List-Unsubscribe"] == f"<{UNSUB}>"
    assert msg["List-Unsubscribe-Post"] == "List-Unsubscribe=One-Click"
    assert msg["Date"]
    assert msg["Message-ID"].endswith("@trattoria.it>")


def test_no_list_unsubscribe_without_url():
    msg = _message(unsubscribe_url=None)

    assert msg["List-Unsubscribe"] is None
    assert msg["List-Unsubscribe-Post"] is None


def test_plain_text_part_before_html():
    parts = _message().get_payload()

    assert [p.get_content_type() for p in parts] == ["text/plain", "text/html"]
    assert "Scopri di più (https://x.it/p)" in parts[0].get_payload(decode=True).decode()


def test_html_to_text_is_readable():
    text = html_to_text(HTML)

    assert text.startswith("Novità & sconti")
    assert "Ciao Mario,\necco le offerte." in text
    assert "color:red" not in text and "pixel" not in text
