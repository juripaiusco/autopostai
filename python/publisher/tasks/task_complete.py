"""Task (SCHELETRO): chiude i post e gestisce il backoff del polling commenti.

Porting da: v1-reference/docker/autopostai/task/task_complete.py
Da implementare in un commit dedicato.

Contratto atteso (come v1):
  - per i post pubblicati non ancora completi: marca task_complete=1 quando ogni
    canale ha raggiunto il cap di risposte (o e' off/senza auto-reply), oppure
    dopo 14 giorni, oppure se superato il limite token dell'utente.
  - altrimenti aggiorna on_hold_until con backoff esponenziale
    min(2^check_attempts minuti, 1 giorno) e incrementa check_attempts.
  - Newsletter/Brevo: salva lo share URL della campagna (integrations/brevo.share_link).
I tunable (14 giorni, base backoff) vanno in publisher/config.py, non hardcoded.
"""

from __future__ import annotations

import logging

from sqlalchemy.engine import Connection

log = logging.getLogger(__name__)


def run(conn: Connection) -> None:  # noqa: ARG001
    log.debug("task_complete: non ancora implementato (scheletro)")
