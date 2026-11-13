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
    assert web_pages.fetch("http://mariadb/").reason == "indirizzo non ammesso"
    assert calls == []


def test_redirect_to_private_ip_is_blocked(web):
    routes, calls = web
    routes["https://shop.example/r"] = lambda: _response(302, "", {"Location": "http://10.0.0.1/admin"})

    assert isinstance(web_pages.fetch("https://shop.example/r"), web_pages.FetchFailure)
    assert calls == ["https://shop.example/r"]


def test_redirect_to_public_page_is_followed(web):
    routes, _ = web
    routes["https://shop.example/r"] = lambda: _response(301, "", {"Location": "/p"})
    routes["https://shop.example/p"] = lambda: _response()

    assert web_pages.fetch("https://shop.example/r").title == "Scarpa Trail X"


def test_non_html_is_ignored(web):
    routes, _ = web
    routes["https://shop.example/f.pdf"] = lambda: _response(headers={"Content-Type": "application/pdf"})

    assert web_pages.fetch("https://shop.example/f.pdf").reason == "non è una pagina web"


def test_text_is_truncated():
    page = web_pages.parse_html("<p>" + "parola " * 2000 + "</p>", "https://shop.example/")
    assert len(page.text) <= web_pages.MAX_TEXT_CHARS + 1


PRODUCT_PAGE = """<html><head><title>Trail X | Shop</title>
<script type="application/ld+json">{"@context": "https://schema.org", "@graph": [
  {"@type": "BreadcrumbList", "name": "briciole"},
  {"@type": ["Product"], "name": "Scarpa Trail X", "description": "<p>Suola <b>Vibram</b>, 280 g.</p>",
   "brand": {"@type": "Brand", "name": "Montura"},
   "image": [{"@type": "ImageObject", "url": "https://shop.example/img/ld.jpg"}],
   "offers": {"@type": "AggregateOffer", "lowPrice": "129.90", "priceCurrency": "EUR",
              "availability": "https://schema.org/InStock"}}]}</script>
</head><body><p>Recensioni, prodotti correlati, cookie...</p></body></html>"""


def test_product_card_from_jsonld_graph():
    page = web_pages.parse_html(PRODUCT_PAGE, "https://shop.example/p")

    assert page.title == "Scarpa Trail X"
    assert page.description == "Suola Vibram , 280 g."
    assert (page.price, page.availability, page.brand) == ("129.90 EUR", "disponibile", "Montura")
    assert page.image == "https://shop.example/img/ld.jpg"
    assert "@graph" not in page.text


def test_og_image_wins_over_jsonld_image():
    html = PRODUCT_PAGE.replace("</head>", '<meta property="og:image" content="/img/og.jpg"></head>')
    assert web_pages.parse_html(html, "https://shop.example/p").image == "https://shop.example/img/og.jpg"


def test_invalid_jsonld_is_ignored_and_meta_price_is_used():
    html = """<html><head><title>Casco</title>
    <script type="application/ld+json">{non valido</script>
    <meta property="product:price:amount" content="59.00"><meta property="product:price:currency" content="EUR">
    <meta name="description" content="Casco leggero"></head><body>testo</body></html>"""
    page = web_pages.parse_html(html, "https://shop.example/c")

    assert (page.price, page.description, page.title) == ("59.00 EUR", "Casco leggero", "Casco")


def test_offer_list_out_of_stock():
    html = """<script type="application/ld+json">[{"@type": "Product", "name": "Tenda",
      "offers": [{"@type": "Offer", "price": 89, "priceCurrency": "EUR",
                  "availability": "http://schema.org/OutOfStock"}], "image": "/t.jpg"}]</script>"""
    page = web_pages.parse_html(html, "https://shop.example/t")

    assert (page.price, page.availability, page.image) == ("89 EUR", "esaurito", "https://shop.example/t.jpg")


def test_card_uses_description_instead_of_raw_text(web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response(body=PRODUCT_PAGE)

    prompt = web_pages.enrich("Newsletter su https://shop.example/p").prompt

    assert "Prezzo: 129.90 EUR · disponibile" in prompt
    assert "Marca: Montura" in prompt
    assert "Descrizione: Suola Vibram" in prompt
    assert "Testo:" not in prompt and "Recensioni" not in prompt
    assert "senza saltarne nessuna" in prompt


def test_raw_text_budget_shrinks_with_many_pages(monkeypatch):
    long_page = web_pages.PageInfo(url="", title="T", text="parola " * 1000, image=None)
    monkeypatch.setattr(web_pages, "fetch", lambda url: long_page)
    monkeypatch.setattr(web_pages, "MAX_URLS", 30)

    def text_lengths(n):
        prompt = web_pages.enrich(" ".join(f"https://shop.example/{i}" for i in range(n))).prompt
        return [len(part.split("\n")[0]) for part in prompt.split("Testo: ")[1:]]

    assert 2900 < text_lengths(1)[0] <= web_pages.MAX_TEXT_CHARS + 1  # pochi link: limite pieno
    many = text_lengths(30)
    assert len(many) == 30
    assert max(many) <= web_pages.TEXT_BUDGET // 30 + 1
    assert sum(many) <= web_pages.TEXT_BUDGET + 30


def test_prompt_without_urls_is_unchanged(monkeypatch):
    monkeypatch.setattr(http, "get", lambda *a, **k: pytest.fail("nessuna chiamata di rete attesa"))
    result = web_pages.enrich("Parla della pizza")
    assert (result.prompt, result.image_paths, result.warnings) == ("Parla della pizza", None, [])


def test_enrich_adds_page_content_and_warns_on_unreadable_pages(web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response()

    result = web_pages.enrich("Newsletter su https://shop.example/p e http://mariadb/x")

    assert "Titolo: Scarpa Trail X" in result.prompt
    assert "Immagine: https://shop.example/img/trail-x.jpg" in result.prompt
    assert "Prezzo 129 €" in result.prompt
    assert "Pagina: http://mariadb/x\nNon leggibile" in result.prompt
    assert "Non inventare mai URL di immagini" in result.prompt
    assert result.image_paths == {"/img/trail-x.jpg"}
    assert result.warnings == [
        "Non è stato possibile leggere mariadb/x: indirizzo non ammesso. "
        "Il testo potrebbe non contenere i dati di quella pagina."
    ]


CLOUDFLARE_CHALLENGE = "<html><head><title>Just a moment...</title></head><body></body></html>"


def test_cloudflare_challenge_is_reported_as_anti_bot(web):
    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response(403, CLOUDFLARE_CHALLENGE, {
        "Content-Type": "text/html; charset=UTF-8", "Server": "cloudflare", "cf-mitigated": "challenge",
    })

    assert web_pages.fetch("https://shop.example/p").reason == "accesso bloccato dal sito (protezione anti-bot)"


def test_plain_403_and_other_errors(web):
    routes, _ = web
    routes["https://shop.example/a"] = lambda: _response(403, "no", {"Content-Type": "text/html", "Server": "nginx"})
    routes["https://shop.example/b"] = lambda: _response(500, "ko")

    assert web_pages.fetch("https://shop.example/a").reason == "accesso negato dal sito"
    assert web_pages.fetch("https://shop.example/b").reason == "errore HTTP 500"


def test_timeout_is_reported(monkeypatch, dns):
    def boom(*a, **k):
        raise requests.Timeout("lento")

    monkeypatch.setattr(http, "get", boom)
    assert web_pages.fetch("https://shop.example/p").reason == "il sito non ha risposto"


# --- Controllo delle immagini nel testo generato -------------------------------

KNOWN = {"/img/trail-x.jpg"}


def test_image_with_changed_domain_but_same_path_is_kept():
    text = "Scarpa\n\n![Trail X](https://img.shop.example/img/trail-x.jpg)"
    assert web_pages.keep_known_images(text, KNOWN) == (text, 0)


def test_invented_markdown_image_is_removed():
    # Il caso reale: percorso ricavato dall'URL .html della pagina.
    text = "Scarpa\n\n![Trail X](https://img.shop.example/scarpe/123-trail-x.jpg)\nFine"
    cleaned, removed = web_pages.keep_known_images(text, KNOWN)
    assert removed == 1
    assert "123-trail-x.jpg" not in cleaned and "Scarpa" in cleaned and "Fine" in cleaned


def test_invented_html_image_is_removed_and_known_one_kept():
    text = ('<p>A</p><img src="https://img.shop.example/x/inventata.jpg" alt="x">'
            "<img alt='ok' src='https://shop.example/img/trail-x.jpg'>")
    cleaned, removed = web_pages.keep_known_images(text, KNOWN)
    assert removed == 1
    assert "inventata" not in cleaned and "trail-x.jpg" in cleaned


def test_unreadable_pages_leave_no_image():
    cleaned, removed = web_pages.keep_known_images("![x](https://img.shop.example/a.jpg)", set())
    assert (cleaned, removed) == ("", 1)


def test_no_urls_in_prompt_means_no_image_check():
    text = "![logo](https://altro.example/logo.png)"
    assert web_pages.keep_known_images(text, None) == (text, 0)


def test_generate_text_endpoint_returns_warnings_and_drops_invented_images(monkeypatch, web):
    from fastapi.testclient import TestClient

    import main

    routes, _ = web
    routes["https://shop.example/p"] = lambda: _response()
    routes["https://altro.example/q"] = lambda: _response(403, CLOUDFLARE_CHALLENGE, {
        "Content-Type": "text/html", "Server": "cloudflare",
    })
    reply = ("# Novità\n\n![A](https://img.shop.example/img/trail-x.jpg)\n\n"
             "![B](https://img.altro.example/q.jpg)")
    monkeypatch.setattr(openai_text, "generate", lambda **kw: (reply, 7))

    res = TestClient(main.app).post("/generate-text", json={
        "api_key": "sk-fake", "channel": "newsletter",
        "ai_prompt_post": "Proponi https://shop.example/p e https://altro.example/q",
    })

    body = res.json()
    assert "img/trail-x.jpg" in body["content"] and "q.jpg" not in body["content"]
    assert body["warnings"] == [
        "Non è stato possibile leggere altro.example/q: accesso bloccato dal sito (protezione anti-bot). "
        "Il testo potrebbe non contenere i dati di quella pagina.",
        "Rimossa un'immagine non presente nelle pagine lette.",
    ]


def test_generate_text_endpoint_social_has_no_warnings(monkeypatch):
    from fastapi.testclient import TestClient

    import main

    monkeypatch.setattr(openai_text, "generate", lambda **kw: ("Testo", 1))
    res = TestClient(main.app).post("/generate-text", json={
        "api_key": "sk-fake", "channel": "facebook", "ai_prompt_post": "Parla di https://shop.example/p",
    })
    assert res.json()["warnings"] == []


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
