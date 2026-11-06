"""Output a schermo per il worker: blocchi compatti per task + log colorati per livello.

Scopo: quando si lancia il worker a mano in test (`dev/run-publisher.sh`) o si
guarda il log di produzione si deve poter "vedere i blocchi" — che task sta
girando, cosa fa, cosa e' andato storto — in una finestra di terminale stretta.
Per questo il formato di default e' compatto (niente timestamp/logger per riga:
l'orario sta nelle righe START/END dello script, la durata nel footer del task):

    ▶ posts_send: 2 post da valutare
        post 12 canale 'facebook' - pubblicato (id=123)
        ✘ ERROR canale 'linkedin' fallito per il post 12
          Traceback (most recent call last): ...
        ↳ posts_send completato (3.21s)

Da crontab (output rediretto a file) i colori si disattivano da soli: nessun
codice ANSI grezzo nei log su disco; WARNING/ERROR restano scritti per esteso
cosi' si trovano con grep.
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


#: prefisso di indentazione delle righe di log di un task, cosi' risultano
#: visivamente "dentro" il blocco aperto dall'intestazione `▶`.
LOG_INDENT = "    "

# Task in corso e se la sua intestazione `▶` e' ancora da stampare: l'intestazione
# non esce in task_start() ma con la prima riga di log del task, che ci si fonde
# (`▶ posts_send: 0 post da valutare`) per risparmiare una riga.
_current_task: str | None = None
_header_pending = False


def _header(text: str) -> str:
    return _wrap(_CYAN + _BOLD, f"▶ {text}")


def task_start(task_name: str) -> float:
    """Apre il blocco del task: ritorna il timestamp di inizio, da passare a
    task_end() per calcolare la durata. L'intestazione esce col primo log."""
    global _current_task, _header_pending
    _current_task = task_name
    _header_pending = True
    return time.monotonic()


def task_end(task_name: str, started: float) -> None:
    global _current_task, _header_pending
    elapsed = time.monotonic() - started
    if _header_pending:  # il task non ha loggato nulla
        print(_header(task_name))
    print(_wrap(_CYAN, f"{LOG_INDENT}↳ {task_name} completato ({elapsed:.2f}s)"))
    print()  # riga vuota: separa visivamente un blocco dal successivo
    _current_task = None
    _header_pending = False


class CompactFormatter(logging.Formatter):
    """Formato di default del worker: solo il messaggio, indentato nel blocco del
    task e senza il prefisso `<task>: ` (gia' nell'intestazione). WARNING/ERROR
    con simbolo + livello (giallo/rosso su TTY), traceback indentato sotto."""

    _TAG_BY_LEVEL = {
        logging.WARNING: ("⚠ WARNING ", _YELLOW),
        logging.ERROR: ("✘ ERROR ", _RED),
        logging.CRITICAL: ("✘ CRITICAL ", _RED + _BOLD),
    }

    def format(self, record: logging.LogRecord) -> str:
        global _header_pending
        message = record.getMessage()
        prefix = f"{_current_task}: " if _current_task else None
        lines: list[str] = []

        if _header_pending:
            _header_pending = False
            if (
                record.levelno == logging.INFO
                and not record.exc_info
                and prefix
                and message.startswith(prefix)
            ):
                return _header(message)
            lines.append(_header(_current_task or ""))

        if prefix and message.startswith(prefix):
            message = message[len(prefix):]

        tag, color = self._TAG_BY_LEVEL.get(record.levelno, ("", ""))
        text = tag + message
        lines.append(LOG_INDENT + (_wrap(color, text) if color else text))

        if record.exc_info:
            trace = self.formatException(record.exc_info)
            lines.extend(LOG_INDENT + "  " + line for line in trace.splitlines())

        return "\n".join(lines)


class ColorFormatter(logging.Formatter):
    """Formato completo (PUBLISHER_DEBUG=1): colora le righe in base al livello,
    INFO neutro, WARNING giallo, ERROR/CRITICAL rosso."""

    _COLOR_BY_LEVEL = {
        logging.WARNING: _YELLOW,
        logging.ERROR: _RED,
        logging.CRITICAL: _RED + _BOLD,
    }

    def format(self, record: logging.LogRecord) -> str:
        global _header_pending
        text = super().format(record)
        if use_color():
            color = self._COLOR_BY_LEVEL.get(record.levelno)
            if color:
                text = f"{color}{text}{_RESET}"
        if _header_pending:  # qui l'intestazione resta una riga a se'
            _header_pending = False
            text = _header(_current_task or "") + "\n" + text
        return text
