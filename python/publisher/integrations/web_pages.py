"""Lettura delle pagine web citate nel prompt (newsletter e WordPress).

Il testo e' generato con chat.completions senza strumenti: il modello non apre
gli URL, li vede solo come testo e finiva per inventare dati e immagini. Qui
FaPer3 scarica le pagine, ne estrae titolo, testo visibile e immagine principale
(og:image) e le aggiunge al prompt, con l'istruzione di usare solo quei dati.

Gli URL li scrive l'utente: ogni salto (anche i redirect) passa dal controllo
SSRF, che ammette solo http/https verso IP pubblici, cosi' dal container non si
raggiungono mariadb, laravel o altri servizi interni.
"""

from __future__ import annotations

import ipaddress
import json
import re
import socket
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse

import requests

from publisher.integrations import http

MAX_URLS = 30
#: letture in parallelo (con 30 link: 4 giri da al massimo ~12 s)
MAX_WORKERS = 8
MAX_REDIRECTS = 3
MAX_BYTES = 2 * 1024 * 1024
MAX_TEXT_CHARS = 3000
#: testo grezzo complessivo nel prompt (solo pagine senza descrizione strutturata),
#: diviso tra le pagine: con pochi link resta MAX_TEXT_CHARS, con molti scende.
TEXT_BUDGET = 24000
MIN_TEXT_CHARS = 400
MAX_DESCRIPTION_CHARS = 600
TIMEOUT = (4, 8)  # connessione, lettura
USER_AGENT = "Mozilla/5.0 (compatible; FaPer3/2.0; +https://faper3.it)"

_URL = re.compile(r"https?://[^\s<>\"'()\[\]]+", re.IGNORECASE)
_SKIP_TAGS = {"script", "style", "noscript", "nav", "footer", "header", "svg", "form", "template"}


@dataclass
class PageInfo:
    url: str
    title: str
    text: str
    image: str | None
    # Scheda prodotto da JSON-LD schema.org / meta product:* (se la pagina li ha).
    description: str | None = None
    price: str | None = None
    availability: str | None = None
    brand: str | None = None


@dataclass
class FetchFailure:
    url: str
    reason: str


@dataclass
class Enrichment:
    prompt: str
    #: percorsi delle immagini lette davvero; None se il prompt non ha URL
    image_paths: set[str] | None
    #: avvisi per l'utente (pagine non leggibili)
    warnings: list[str]


def extract_urls(prompt: str) -> list[str]:
    """URL http/https del prompt, senza duplicati, al massimo MAX_URLS."""
    return _all_urls(prompt)[:MAX_URLS]


def _all_urls(prompt: str) -> list[str]:
    urls: list[str] = []
    for match in _URL.finditer(prompt or ""):
        url = match.group(0).rstrip(".,;:!?")
        if url not in urls:
            urls.append(url)
    return urls


def is_public_url(url: str) -> bool:
    """True se lo schema e' http/https e tutti gli IP dell'host sono pubblici."""
    parsed = urlparse(url)
    if parsed.scheme not in ("http", "https") or not parsed.hostname:
        return False
    try:
        infos = socket.getaddrinfo(parsed.hostname, parsed.port or None, proto=socket.IPPROTO_TCP)
    except (socket.gaierror, UnicodeError, ValueError):
        return False
    if not infos:
        return False
    for info in infos:
        ip = ipaddress.ip_address(info[4][0].split("%", 1)[0])
        if not ip.is_global or ip.is_multicast:
            return False
    return True


def fetch(url: str) -> PageInfo | FetchFailure:
    """Scarica e analizza la pagina; FetchFailure con un motivo leggibile se non si puo'."""
    current = url
    try:
        for _ in range(MAX_REDIRECTS + 1):
            if not is_public_url(current):
                return FetchFailure(url, "indirizzo non ammesso")
            resp = http.get(
                current,
                timeout=TIMEOUT,
                stream=True,
                allow_redirects=False,
                headers={"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml"},
            )
            if resp.is_redirect and resp.headers.get("Location"):
                current = urljoin(current, resp.headers["Location"])
                resp.close()
                continue
            break
        else:
            return FetchFailure(url, "troppi reindirizzamenti")

        with resp:
            if resp.status_code != 200:
                return FetchFailure(url, _status_reason(resp))
            if "html" not in (resp.headers.get("Content-Type") or "").lower():
                return FetchFailure(url, "non è una pagina web")
            body = b""
            for chunk in resp.iter_content(chunk_size=65536):
                body += chunk
                if len(body) >= MAX_BYTES:
                    break
            # Senza charset nell'header requests ipotizza ISO-8859-1 per text/*:
            # qui si preferisce UTF-8, il caso di gran lunga piu' comune.
            charset = re.search(r"charset=([\w-]+)", resp.headers.get("Content-Type") or "", re.IGNORECASE)
            encoding = charset.group(1) if charset else "utf-8"
    except requests.RequestException:
        return FetchFailure(url, "il sito non ha risposto")

    try:
        html = body.decode(encoding, errors="replace")
    except LookupError:
        html = body.decode("utf-8", errors="replace")
    return parse_html(html, current)


def _status_reason(resp) -> str:
    """403/429/503 da Cloudflare (verifica "Just a moment...", Under Attack) = blocco anti-bot."""
    server = (resp.headers.get("Server") or "").lower()
    if resp.status_code in (403, 429, 503) and ("cloudflare" in server or resp.headers.get("cf-mitigated")):
        return "accesso bloccato dal sito (protezione anti-bot)"
    if resp.status_code == 403:
        return "accesso negato dal sito"
    return f"errore HTTP {resp.status_code}"


def parse_html(html: str, base_url: str) -> PageInfo:
    parser = _PageParser()
    parser.feed(html)
    parser.close()
    meta = parser.meta
    product = _product_from_jsonld(parser.jsonld) or {}

    text = _truncate(re.sub(r"\s+", " ", " ".join(parser.text)).strip(), MAX_TEXT_CHARS)
    image = meta.get("og:image") or meta.get("og:image:url") or meta.get("twitter:image") or product.get("image")
    image = urljoin(base_url, image.strip()) if image else None
    if image and urlparse(image).scheme not in ("http", "https"):
        image = None
    title = (meta.get("og:title") or product.get("name") or " ".join(parser.title)).strip()

    description = product.get("description") or meta.get("og:description") or meta.get("description")
    description = _truncate(_clean(description), MAX_DESCRIPTION_CHARS) or None
    price = product.get("price")
    if not price and meta.get("product:price:amount"):
        price = " ".join(filter(None, [meta["product:price:amount"], meta.get("product:price:currency")]))
    return PageInfo(
        url=base_url,
        title=re.sub(r"\s+", " ", title),
        text=text,
        image=image,
        description=description,
        price=price or None,
        availability=product.get("availability"),
        brand=product.get("brand"),
    )


def _clean(value) -> str:
    """Testo di un campo strutturato: senza tag HTML residui e spazi multipli."""
    if not isinstance(value, str):
        return ""
    parser = _PageParser()
    parser.feed(value)
    parser.close()
    return re.sub(r"\s+", " ", " ".join(parser.text)).strip()


def _truncate(text: str, limit: int) -> str:
    return text if len(text) <= limit else text[:limit].rsplit(" ", 1)[0] + "…"


_PRODUCT_TYPES = {"product", "productgroup"}


def _product_from_jsonld(blocks: list[str]) -> dict | None:
    """Primo oggetto schema.org Product/ProductGroup dei blocchi ld+json:
    nome, descrizione, marca, prezzo, disponibilita' e immagine. JSON non valido
    o senza prodotto: None (si ripiega sui meta e sul testo)."""
    for block in blocks:
        try:
            data = json.loads(block)
        except ValueError:
            continue
        for node in _jsonld_nodes(data):
            types = node.get("@type")
            types = types if isinstance(types, list) else [types]
            if any(isinstance(t, str) and t.lower() in _PRODUCT_TYPES for t in types):
                return _product_fields(node)
    return None


def _jsonld_nodes(data):
    """Tutti gli oggetti di un blocco: lista in testa e @graph."""
    if isinstance(data, list):
        for item in data:
            yield from _jsonld_nodes(item)
    elif isinstance(data, dict):
        yield data
        if "@graph" in data:
            yield from _jsonld_nodes(data["@graph"])


def _product_fields(node: dict) -> dict:
    brand = node.get("brand")
    if isinstance(brand, list):
        brand = brand[0] if brand else None
    if isinstance(brand, dict):
        brand = brand.get("name")

    offers = node.get("offers")
    if isinstance(offers, list):
        offers = offers[0] if offers else None
    price = availability = None
    if isinstance(offers, dict):
        amount = offers.get("price") or offers.get("lowPrice")
        if amount is None and isinstance(offers.get("priceSpecification"), dict):
            amount = offers["priceSpecification"].get("price")
        if amount not in (None, ""):
            price = " ".join(filter(None, [str(amount), offers.get("priceCurrency")]))
        state = str(offers.get("availability") or "").rsplit("/", 1)[-1].lower()
        if state:
            availability = "disponibile" if state in ("instock", "limitedavailability", "onlineonly") else (
                "esaurito" if state in ("outofstock", "soldout", "discontinued") else None)

    image = node.get("image")
    if isinstance(image, list):
        image = image[0] if image else None
    if isinstance(image, dict):
        image = image.get("url") or image.get("contentUrl")

    return {
        "name": node.get("name") if isinstance(node.get("name"), str) else None,
        "description": node.get("description"),
        "brand": brand if isinstance(brand, str) else None,
        "price": price,
        "availability": availability,
        "image": image if isinstance(image, str) else None,
    }


def enrich(prompt: str) -> Enrichment:
    """Aggiunge al prompt il contenuto delle pagine citate. Senza URL il prompt
    resta invariato e non parte nessuna chiamata di rete (image_paths None:
    nessun controllo sulle immagini del testo generato)."""
    found = _all_urls(prompt)
    if not found:
        return Enrichment(prompt, None, [])
    urls, skipped = found[:MAX_URLS], found[MAX_URLS:]

    with ThreadPoolExecutor(max_workers=min(len(urls), MAX_WORKERS)) as pool:
        pages = list(pool.map(fetch, urls))

    # Testo grezzo solo come ripiego, diviso tra le pagine: con molti link il
    # prompt resta corto e il modello non perde pagine per strada.
    text_chars = max(MIN_TEXT_CHARS, min(MAX_TEXT_CHARS, TEXT_BUDGET // len(urls)))
    blocks, image_paths, warnings = [], set(), []
    for url, page in zip(urls, pages):
        if isinstance(page, FetchFailure):
            warnings.append(f"Non è stato possibile leggere {_short(url)}: {page.reason}. "
                            "Il testo potrebbe non contenere i dati di quella pagina.")
            blocks.append(f"Pagina: {url}\nNon leggibile: non inventare dati su questa pagina, "
                          "non inserire immagini per questa pagina e scrivi che non è stato possibile leggerla.")
            continue
        if page.image:
            image_paths.add(urlparse(page.image).path)
        blocks.append(_card(url, page, text_chars))

    # Oltre il limite: il modello vede comunque l'URL nel prompt, quindi va detto
    # che non e' stato letto, altrimenti ne inventa il contenuto.
    for url in skipped:
        blocks.append(f"Pagina: {url}\nNon letta (oltre il limite di {MAX_URLS} link): non inventare dati su questa "
                      "pagina e non inserire immagini per questa pagina.")
    if skipped:
        warnings.append(f"Lette solo le prime {MAX_URLS} pagine indicate: "
                        + (f"ignorata {_short(skipped[0])}." if len(skipped) == 1
                           else f"ignorate le altre {len(skipped)} ({_short(skipped[0])} e seguenti).")
                        + " Dividi i link in più post o riducili.")

    instructions = (
        "Contenuto delle pagine indicate (letto da FaPer3, il modello non può aprire i link). "
        "Usa solo queste informazioni per i dati delle pagine: non inventare prezzi, caratteristiche o dettagli. "
        "Per ogni pagina che ha un'immagine, inserisci l'immagine sotto la parte che ne parla con la sintassi "
        "Markdown ![titolo](url immagine), usando l'URL indicato in \"Immagine:\". Se le istruzioni chiedono di "
        "cambiare il dominio delle immagini puoi cambiare solo il dominio, mai il percorso del file. "
        "Non inventare mai URL di immagini e non inserire immagini per le pagine che non ne hanno. "
        "Dedica una parte a ogni pagina, nell'ordine dato, senza saltarne nessuna."
    )
    return Enrichment(f"{prompt}\n\n{instructions}\n\n" + "\n\n---\n\n".join(blocks), image_paths, warnings)


def _card(url: str, page: PageInfo, text_chars: int) -> str:
    """Scheda compatta della pagina: dati strutturati se ci sono, altrimenti testo troncato."""
    lines = [f"Pagina: {url}", f"Titolo: {page.title or '(nessuno)'}"]
    if page.price:
        lines.append("Prezzo: " + " · ".join(filter(None, [page.price, page.availability])))
    elif page.availability:
        lines.append(f"Disponibilità: {page.availability}")
    if page.brand:
        lines.append(f"Marca: {page.brand}")
    lines.append(f"Immagine: {page.image}" if page.image else "Immagine: nessuna")
    if page.description:
        lines.append(f"Descrizione: {page.description}")
    else:
        lines.append(f"Testo: {_truncate(page.text, text_chars) or '(vuoto)'}")
    return "\n".join(lines)


_MD_IMAGE = re.compile(r"!\[[^\]]*\]\(\s*<?([^)\s>]+)>?(?:\s+\"[^\"]*\")?\s*\)")
_HTML_IMAGE = re.compile(r"<img\b[^>]*?\bsrc\s*=\s*([\"'])(.*?)\1[^>]*>", re.IGNORECASE)


def keep_known_images(text: str, image_paths: set[str] | None) -> tuple[str, int]:
    """Toglie dal testo generato le immagini (Markdown o <img>) il cui percorso
    non e' quello di un'immagine letta davvero. Il dominio puo' cambiare (es.
    www -> img chiesto dall'utente), il file no. Ritorna (testo, rimosse).
    image_paths None = nessun URL nel prompt: testo intatto."""
    if image_paths is None:
        return text, 0
    removed = 0

    def check(match, url):
        nonlocal removed
        if urlparse(url.strip()).path in image_paths:
            return match.group(0)
        removed += 1
        return ""

    text = _MD_IMAGE.sub(lambda m: check(m, m.group(1)), text)
    text = _HTML_IMAGE.sub(lambda m: check(m, m.group(2)), text)
    return text, removed


def removed_images_warning(removed: int) -> str:
    return ("Rimossa un'immagine non presente nelle pagine lette." if removed == 1
            else f"Rimosse {removed} immagini non presenti nelle pagine lette.")


def _short(url: str) -> str:
    parsed = urlparse(url)
    path = parsed.path if len(parsed.path) <= 60 else parsed.path[:57] + "…"
    return f"{parsed.hostname}{path}"


class _PageParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.meta: dict[str, str] = {}
        self.title: list[str] = []
        self.text: list[str] = []
        #: contenuto dei <script type="application/ld+json"> (dati schema.org)
        self.jsonld: list[str] = []
        self._skip = 0
        self._in_title = False
        self._in_jsonld = False

    def handle_starttag(self, tag, attrs):
        if tag == "meta":
            a = dict(attrs)
            key = (a.get("property") or a.get("name") or "").lower()
            if key and a.get("content") and key not in self.meta:
                self.meta[key] = a["content"]
        elif tag == "title":
            self._in_title = True
        elif tag in _SKIP_TAGS:
            self._skip += 1
            if tag == "script" and (dict(attrs).get("type") or "").lower().strip() == "application/ld+json":
                self._in_jsonld = True
                self.jsonld.append("")

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        elif tag == "script" and self._in_jsonld:
            self._in_jsonld = False
            if self._skip:
                self._skip -= 1
        elif tag in _SKIP_TAGS and self._skip:
            self._skip -= 1

    def handle_data(self, data):
        if self._in_jsonld:
            self.jsonld[-1] += data
        elif self._in_title:
            self.title.append(data)
        elif not self._skip:
            self.text.append(data)
