"""Lettura tipizzata del JSON `channels` (shape v2).

In v1 i canali erano identificati da un campo `name` ('Facebook'…) e i flag erano
stringhe confrontate come `== '1'` (con un bug latente: un punto confrontava `== 1`
int). In v2 il canale e' la CHIAVE del dizionario (`facebook`, `instagram`,
`linkedin`, `wordpress`, `newsletter`), `on` e' un vero booleano, e `id`/`url` non
esistono finche' il worker non li scrive dopo la pubblicazione.

Shape (da PostController::buildChannelsPayload di Laravel):
    facebook/instagram/linkedin : {on, comments_enabled, auto_reply_enabled}
    wordpress                   : {on, categories:[{id,name,on}]}
    newsletter                  : {on, list:{provider,id,name}}
    non selezionato             : {on:false}
Dopo la pubblicazione il worker aggiunge: id, url (+ gallery_html per wordpress).
"""

from __future__ import annotations

import json
from dataclasses import dataclass

# Chiavi canale note e ordine di elaborazione (i social prima, poi web/newsletter).
CHANNEL_KEYS = ("facebook", "instagram", "linkedin", "wordpress", "newsletter")


@dataclass
class ChannelEntry:
    key: str
    data: dict

    @property
    def is_on(self) -> bool:
        return self.data.get("on") is True

    @property
    def already_published(self) -> bool:
        """True se il canale ha gia' un id remoto (pubblicato in un run precedente)."""
        return self.data.get("id") is not None

    @property
    def needs_publish(self) -> bool:
        return self.is_on and not self.already_published

    def set_result(self, remote_id, url, gallery_html=None) -> None:
        self.data["id"] = remote_id
        self.data["url"] = url
        if gallery_html is not None:
            self.data["gallery_html"] = gallery_html

    # --- accessori specifici per canale ---
    def wordpress_category_ids(self) -> list:
        return [c["id"] for c in self.data.get("categories", []) if c.get("on") is True]

    def newsletter_provider(self) -> str | None:
        return (self.data.get("list") or {}).get("provider")

    def newsletter_list_id(self):
        return (self.data.get("list") or {}).get("id")

    @property
    def comments_enabled(self) -> bool:
        return self.data.get("comments_enabled") is True

    @property
    def auto_reply_enabled(self) -> bool:
        return self.data.get("auto_reply_enabled") is True

    @property
    def reply_n(self) -> int | None:
        """Tetto di commenti/risposte per questo canale, congelato nel post alla
        creazione (arriva da users.channels — vedi PostController::buildChannelsPayload).
        None/0 => nessun cap configurato, il canale non richiede monitoraggio continuo."""
        value = self.data.get("reply_n")
        return int(value) if value not in (None, "") else None

    @property
    def monitoring_needed(self) -> bool:
        """Il canale richiede polling commenti/reply se acceso, con commenti abilitati
        e un cap configurato (> 0). Senza cap non si sa quando fermarsi: come v1,
        si considera 'non da monitorare' invece di interrogare le API all'infinito."""
        return self.is_on and self.comments_enabled and bool(self.reply_n)

    def needs_comment_fetch(self, current_count: int) -> bool:
        """True se vale la pena interrogare il provider per nuovi commenti
        (sotto al cap configurato)."""
        return self.monitoring_needed and current_count < (self.reply_n or 0)

    def social_monitoring_complete(self, current_count: int) -> bool:
        """True quando il canale (facebook/instagram/linkedin) non richiede piu'
        controlli: spento, commenti disabilitati, nessun cap configurato, o cap
        raggiunto. Porting di v1 task_complete.py, adattato allo split
        comments_enabled/reply_n di v2."""
        if not self.is_on:
            return True
        if not self.comments_enabled:
            return True
        if not self.reply_n:
            return True
        return current_count >= self.reply_n


class Channels:
    """Wrapper sul dizionario channels con iterazione tipizzata."""

    def __init__(self, raw: dict):
        self._raw = raw

    @classmethod
    def parse(cls, value) -> "Channels":
        """Accetta sia la stringa JSON dal DB sia un dict gia' decodificato."""
        if isinstance(value, str):
            value = json.loads(value)
        return cls(value or {})

    def entry(self, key: str) -> ChannelEntry | None:
        data = self._raw.get(key)
        return ChannelEntry(key, data) if isinstance(data, dict) else None

    def publishable(self) -> list[ChannelEntry]:
        """I canali accesi e non ancora pubblicati, in ordine canonico."""
        out = []
        for key in CHANNEL_KEYS:
            entry = self.entry(key)
            if entry and entry.needs_publish:
                out.append(entry)
        return out

    def all_on_published(self) -> bool:
        """True se ogni canale ACCESO ha un id remoto (=> post pubblicato)."""
        for key in CHANNEL_KEYS:
            entry = self.entry(key)
            if entry and entry.is_on and not entry.already_published:
                return False
        return True

    def to_dict(self) -> dict:
        return self._raw
