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
import re
import socket
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse

import requests

from publisher.integrations import http

MAX_URLS = 5
MAX_REDIRECTS = 3
MAX_BYTES = 2 * 1024 * 1024
MAX_TEXT_CHARS = 3000
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


def extract_urls(prompt: str) -> list[str]:
    """URL http/https del prompt, senza duplicati, al massimo MAX_URLS."""
    urls: list[str] = []
    for match in _URL.finditer(prompt or ""):
        url = match.group(0).rstrip(".,;:!?")
        if url not in urls:
            urls.append(url)
    return urls[:MAX_URLS]


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


def fetch(url: str) -> PageInfo | None:
    """Scarica e analizza la pagina; None se non raggiungibile, non HTML o non pubblica."""
    current = url
    try:
        for _ in range(MAX_REDIRECTS + 1):
            if not is_public_url(current):
                return None
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
            return None

        with resp:
            if resp.status_code != 200:
                return None
            if "html" not in (resp.headers.get("Content-Type") or "").lower():
                return None
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
        return None

    try:
        html = body.decode(encoding, errors="replace")
    except LookupError:
        html = body.decode("utf-8", errors="replace")
    return parse_html(html, current)


def parse_html(html: str, base_url: str) -> PageInfo:
    parser = _PageParser()
    parser.feed(html)
    parser.close()
    text = re.sub(r"\s+", " ", " ".join(parser.text)).strip()
    if len(text) > MAX_TEXT_CHARS:
        text = text[:MAX_TEXT_CHARS].rsplit(" ", 1)[0] + "…"
    image = parser.meta.get("og:image") or parser.meta.get("og:image:url") or parser.meta.get("twitter:image")
    image = urljoin(base_url, image.strip()) if image else None
    if image and urlparse(image).scheme not in ("http", "https"):
        image = None
    title = (parser.meta.get("og:title") or " ".join(parser.title)).strip()
    return PageInfo(url=base_url, title=re.sub(r"\s+", " ", title), text=text, image=image)


def enrich_prompt(prompt: str) -> str:
    """Aggiunge al prompt il contenuto delle pagine citate. Senza URL lo
    restituisce invariato, senza chiamate di rete."""
    urls = extract_urls(prompt)
    if not urls:
        return prompt

    with ThreadPoolExecutor(max_workers=len(urls)) as pool:
        pages = list(pool.map(fetch, urls))

    blocks = []
    for url, page in zip(urls, pages):
        if page is None:
            blocks.append(f"Pagina: {url}\nNon raggiungibile: non inventare dati su questa pagina, scrivi che non è stato possibile leggerla.")
            continue
        lines = [f"Pagina: {url}", f"Titolo: {page.title or '(nessuno)'}"]
        lines.append(f"Immagine: {page.image}" if page.image else "Immagine: nessuna")
        lines.append(f"Testo: {page.text or '(vuoto)'}")
        blocks.append("\n".join(lines))

    instructions = (
        "Contenuto delle pagine indicate (letto da FaPer3, il modello non può aprire i link). "
        "Usa solo queste informazioni per i dati delle pagine: non inventare prezzi, caratteristiche o dettagli. "
        "Per ogni pagina che ha un'immagine, inserisci l'immagine sotto la parte che ne parla con la sintassi "
        "Markdown ![titolo](url immagine), usando esattamente l'URL indicato in \"Immagine:\". "
        "Non inventare mai URL di immagini e non inserire immagini per le pagine che non ne hanno."
    )
    return f"{prompt}\n\n{instructions}\n\n" + "\n\n---\n\n".join(blocks)


class _PageParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.meta: dict[str, str] = {}
        self.title: list[str] = []
        self.text: list[str] = []
        self._skip = 0
        self._in_title = False

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

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        elif tag in _SKIP_TAGS and self._skip:
            self._skip -= 1

    def handle_data(self, data):
        if self._in_title:
            self.title.append(data)
        elif not self._skip:
            self.text.append(data)
