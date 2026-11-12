"""Unit test: lettura delle pagine citate nel prompt (newsletter e WordPress).

Il modello non apre i link (chat.completions senza strumenti): FaPer3 scarica le
pagine e ne passa testo e og:image nel prompt. Gli URL li scrive l'utente, quindi
ogni salto passa dal controllo SSRF (solo IP pubblici). Niente rete reale: DNS e
HTTP sono simulati.
"""

import socket

import pytest
import requests

from publisher import config
from publisher.content import ContentService
from publisher.integrations import http, openai_text, web_pages
from publisher.publishing.facebook import FacebookPublisher

PUBLIC_IP = "93.184.216.34"

PAGE = """<html><head><title>Scarpa Trail X</title>
<meta property="og:image" content="/img/trail-x.jpg">
<script>var nascosto = 1;</script></head>
<body><nav>Menu Home Shop</nav><h1>Scarpa Trail X</h1><p>Prezzo 129 €. Suola Vibram.</p></body></html>"""


def _response(status=200, body=PAGE, headers=None):
    resp = requests.Response()
    resp.status_code = status
    resp._content = body.encode("utf-8")
    resp._content_consumed = True
    resp.headers.update(headers or {"Content-Type": "text/html; charset=utf-8"})
    return resp


@pytest.fixture
def dns(monkeypatch):
    """Nome host -> IP; i nomi non mappati non si risolvono."""
    table = {"shop.example": PUBLIC_IP, "altro.example": PUBLIC_IP, "mariadb": "172.18.0.3", "interno.example": "10.0.0.5"}

    def fake_getaddrinfo(host, port, *args, **kwargs):
        try:
            ip = str(__import__("ipaddress").ip_address(host))
        except ValueError:
            if host not in table:
                raise socket.gaierror("unknown host")
            ip = table[host]
        return [(socket.AF_INET, socket.SOCK_STREAM, 6, "", (ip, port or 80))]

    monkeypatch.setattr(socket, "getaddrinfo", fake_getaddrinfo)


@pytest.fixture
def web(monkeypatch, dns):
    """URL -> risposta finta; registra le richieste fatte."""
    routes, calls = {}, []

    def fake_get(url, **kwargs):
        calls.append(url)
        assert kwargs["allow_redirects"] is False
        assert kwargs["timeout"]
        return routes[url]() if url in routes else _response(404, "")

    monkeypatch.setattr(http, "get", fake_get)
    return routes, calls


def test_extract_urls_dedupes_strips_punctuation_and_caps():
    prompt = "Vedi https://shop.example/a, poi https://shop.example/a e (http://altro.example/b)."
    assert web_pages.extract_urls(prompt) == ["https://shop.example/a", "http://altro.example/b"]

    many = " ".join(f"https://shop.example/{i}" for i in range(9))
    assert len(web_pages.extract_urls(many)) == web_pages.MAX_URLS


@pytest.mark.parametrize("url", [
    "http://127.0.0.1/", "http://10.1.2.3/x", "http://169.254.169.254/latest/meta-data",
    "http://192.168.1.1/", "http://[::1]/", "http://mariadb/", "http://interno.example/",
    "ftp://shop.example/file", "http://host-inesistente/",
])
def test_non_public_urls_are_rejected(dns, url):
    assert web_pages.is_public_url(url) is False


def test_public_url_is_accepted(dns):
    assert web_pages.is_public_url("https://shop.example/p") is True


def test_fetch_parses_title_text_and_absolute_og_image(web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response()

    page = web_pages.fetch("https://shop.example/p")

    assert page.title == "Scarpa Trail X"
    assert page.image == "https://shop.example/img/trail-x.jpg"
    assert "Prezzo 129 €" in page.text
    assert "nascosto" not in page.text and "Menu Home" not in page.text


def test_private_url_is_never_requested(web):
    _, calls = web
    assert web_pages.fetch("http://mariadb/") is None
    assert calls == []


def test_redirect_to_private_ip_is_blocked(web):
    routes, calls = web
    routes["https://shop.example/r"] = lambda: _response(302, "", {"Location": "http://10.0.0.1/admin"})

    assert web_pages.fetch("https://shop.example/r") is None
    assert calls == ["https://shop.example/r"]


def test_redirect_to_public_page_is_followed(web):
    routes, _ = web
    routes["https://shop.example/r"] = lambda: _response(301, "", {"Location": "/p"})
    routes["https://shop.example/p"] = lambda: _response()

    assert web_pages.fetch("https://shop.example/r").title == "Scarpa Trail X"


def test_non_html_is_ignored(web):
    routes, _ = web
    routes["https://shop.example/f.pdf"] = lambda: _response(headers={"Content-Type": "application/pdf"})

    assert web_pages.fetch("https://shop.example/f.pdf") is None


def test_text_is_truncated():
    page = web_pages.parse_html("<p>" + "parola " * 2000 + "</p>", "https://shop.example/")
    assert len(page.text) <= web_pages.MAX_TEXT_CHARS + 1


def test_prompt_without_urls_is_unchanged(monkeypatch):
    monkeypatch.setattr(http, "get", lambda *a, **k: pytest.fail("nessuna chiamata di rete attesa"))
    assert web_pages.enrich_prompt("Parla della pizza") == "Parla della pizza"


def test_enrich_prompt_adds_page_content_and_unreachable_notice(web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response()

    prompt = web_pages.enrich_prompt("Newsletter su https://shop.example/p e http://mariadb/x")

    assert "Titolo: Scarpa Trail X" in prompt
    assert "Immagine: https://shop.example/img/trail-x.jpg" in prompt
    assert "Prezzo 129 €" in prompt
    assert "Pagina: http://mariadb/x\nNon raggiungibile" in prompt
    assert "Non inventare mai URL di immagini" in prompt


# --- Aggancio al worker: solo newsletter/WordPress leggono i link -----------------

class _Repo:
    def save_ai_content(self, *a):
        pass

    def log(self, *a):
        pass


@pytest.fixture
def worker_prompts(monkeypatch, web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response()
    sent = []
    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(openai_text, "generate", lambda **kw: (sent.append(kw["user_prompt"]), ("Testo", 1))[1])
    return sent


def _resolve(channels):
    post = {
        "id": 1, "user_id": 2, "ai_prompt_post": "Parla di https://shop.example/p", "ai_content": None,
        "img": None, "img_ai_check_on": "0", "openai_api_key": "sk-fake", "channels": channels,
    }
    ContentService(_Repo(), _Repo()).resolve(post, FacebookPublisher(post), lambda *a: "")


def test_worker_newsletter_prompt_includes_page_content(worker_prompts):
    _resolve({"facebook": {"on": True}, "newsletter": {"on": True}})
    assert "Titolo: Scarpa Trail X" in worker_prompts[0]


def test_worker_social_only_prompt_is_not_enriched(worker_prompts):
    _resolve({"facebook": {"on": True}})
    assert worker_prompts == ["Parla di https://shop.example/p"]
