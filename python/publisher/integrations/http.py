"""Chiamate HTTP verso i provider esterni, sempre con timeout.

`requests` senza `timeout=` aspetta per sempre: un'API che non risponde teneva
fermo il run del worker e, con il lock tra run (db/engine.run_lock), anche tutti i
run successivi. Stessa firma di requests.get/post/put/delete; il timeout di
default (connessione, lettura) e' in config e resta sovrascrivibile per chiamata.
"""

from __future__ import annotations

import requests

from publisher import config


def get(url: str, **kwargs) -> requests.Response:
    return requests.get(url, **_with_timeout(kwargs))


def post(url: str, **kwargs) -> requests.Response:
    return requests.post(url, **_with_timeout(kwargs))


def put(url: str, **kwargs) -> requests.Response:
    return requests.put(url, **_with_timeout(kwargs))


def delete(url: str, **kwargs) -> requests.Response:
    return requests.delete(url, **_with_timeout(kwargs))


def _with_timeout(kwargs: dict) -> dict:
    kwargs.setdefault("timeout", config.HTTP_TIMEOUT)
    return kwargs
