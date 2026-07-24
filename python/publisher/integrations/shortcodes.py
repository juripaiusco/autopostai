"""Parsing degli shortcode nei contenuti generati.

Consolida logica che in v1 era duplicata quasi identica in tre punti
(`task/ai_content_get.py`, `services/mailchimp.py`, `services/brevo.py`):
  - cross-link  `[Canale url id=N]`  -> URL del post pubblicato su quel canale
  - call-to-action `[cta url id=N text="..."]` -> HTML dal template CTA dell'account

La risoluzione dell'URL e' delegata a un `url_resolver(post_id, channel_key,
data_type)` iniettato da chi chiama, cosi' questo modulo non tocca il DB.
"""

from __future__ import annotations

import re
from typing import Callable

UrlResolver = Callable[[int, str, str], str]

_CROSSLINK = re.compile(r"\[(\w+)\s+(\w+)\s+id=(\d+)\]")
_CTA = re.compile(r"\[cta(.*?)\]")
# key=value con valore fra virgolette (singole/doppie) o senza spazi.
_ATTR = re.compile(r"""(\w+)=(?:"([^"]*)"|'([^']*)'|(\S+))""")
# parole "flag" (senza =): il nome del canale e il flag `url`.
_WORD = re.compile(r"[A-Za-z]+")

# Token di canale usati negli shortcode (case-insensitive) -> chiave v2.
_CHANNEL_TOKENS = {
    "facebook": "facebook",
    "instagram": "instagram",
    "linkedin": "linkedin",
    "wordpress": "wordpress",
    "newsletter": "newsletter",
}


def parse_crosslinks(text: str, url_resolver: UrlResolver) -> str:
    """Sostituisce `[Canale url id=N]` con l'URL reale del post su quel canale."""

    def repl(match: re.Match) -> str:
        channel_token, data_type, post_id = match.groups()
        if data_type.lower() != "url":
            return match.group(0)
        key = _CHANNEL_TOKENS.get(channel_token.lower())
        if key is None:
            return match.group(0)
        return url_resolver(int(post_id), key, "url")

    return _CROSSLINK.sub(repl, text)


def parse_cta(text: str, template_cta: str, url_resolver: UrlResolver) -> str:
    """Espande `[cta ...]` nell'HTML del template CTA dell'account."""

    def repl(match: re.Match) -> str:
        raw = match.group(1)

        attrs: dict[str, str] = {}
        for m in _ATTR.finditer(raw):
            attrs[m.group(1)] = m.group(2) if m.group(2) is not None else m.group(3) if m.group(3) is not None else m.group(4)

        # I "flag" (parole senza =) sono cio' che resta tolte le coppie key=value.
        flags = {w.lower() for w in _WORD.findall(_ATTR.sub(" ", raw))}

        text_value = attrs.get("text") or ""
        url_value = attrs.get("url")

        # `url` come flag (senza valore) => risolvi dal post indicato.
        if url_value is None and "url" in flags:
            post_id = attrs.get("id")
            channel = next((_CHANNEL_TOKENS[f] for f in flags if f in _CHANNEL_TOKENS), None)
            url_value = url_resolver(int(post_id), channel, "url") if (post_id and channel) else ""

        return (template_cta or "").replace("[text]", text_value).replace("[url]", url_value or "")

    return _CTA.sub(repl, text)
