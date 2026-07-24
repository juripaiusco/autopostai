"""Output a schermo per il worker: banner a blocchi + log colorati per livello.

Scopo: quando si lancia il worker a mano in test (`dev/run-publisher.sh`) si deve
poter "vedere i blocchi" — che task sta girando, cosa fa canale per canale, cosa e'
andato storto — proprio come i print(debug=True) di v1. Da crontab (output
rediretto a file) i colori si disattivano da soli: nessun codice ANSI grezzo nei
log su disco.
"""

from __future__ import annotations

import logging
import os
import sys
import time

_RESET = "\033[0m"
_BOLD = "\033[1m"
_CYAN = "\033[36m"
_GREEN = "\033[32m"
_YELLOW = "\033[33m"
_RED = "\033[31m"


def use_color() -> bool:
    """Colori attivi solo su un vero terminale, e solo se NO_COLOR non e' impostata
    (convenzione standard: https://no-color.org/). Su file/pipe (crontab) resta testo puro."""
    if os.environ.get("NO_COLOR"):
        return False
    return sys.stdout.isatty()


def _wrap(code: str, text: str) -> str:
    return f"{code}{text}{_RESET}" if use_color() else text


def banner_start(task_name: str) -> float:
    """Stampa l'intestazione del blocco task e ritorna il timestamp di inizio
    (da passare a banner_end per calcolare la durata)."""
    started = time.monotonic()
    stamp = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"┏━━━ {task_name} — {stamp} ━━━┓"
    print(_wrap(_CYAN + _BOLD, line))
    return started


def banner_end(task_name: str, started: float) -> None:
    elapsed = time.monotonic() - started
    line = f"┗━━━ {task_name} — fine ({elapsed:.2f}s) ━━━┛"
    print(_wrap(_CYAN + _BOLD, line))


class ColorFormatter(logging.Formatter):
    """Colora le righe di log in base al livello: INFO neutro, WARNING giallo,
    ERROR/CRITICAL rosso — cosi' un fallimento salta all'occhio dentro il blocco."""

    _COLOR_BY_LEVEL = {
        logging.WARNING: _YELLOW,
        logging.ERROR: _RED,
        logging.CRITICAL: _RED + _BOLD,
    }

    def format(self, record: logging.LogRecord) -> str:
        text = super().format(record)
        if not use_color():
            return text
        color = self._COLOR_BY_LEVEL.get(record.levelno)
        return f"{color}{text}{_RESET}" if color else text
