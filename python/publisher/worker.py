"""Entry point del worker di pubblicazione (cron one-shot).

Come v1: viene lanciato una volta (ogni minuto, da cron via run-publisher.sh),
esegue i task in ordine su UNA sola connessione e termina. Nessun loop interno: la
cadenza la da' l'orchestratore esterno.

    python -m publisher.worker

Un solo run alla volta (lock MariaDB, vedi db/engine.run_lock): se il run
precedente e' ancora in corso questo esce subito. Commit dopo ogni task e dentro i
task ad ogni azione remota (db/engine.checkpoint). I task sono eseguiti in ordine;
ognuno isola i propri errori cosi' che il fallimento di uno non impedisca agli
altri di girare.
"""

from __future__ import annotations

import logging
import sys

from publisher import cli_output, config
from publisher.db.engine import checkpoint, connection, run_lock
from publisher.tasks import (
    comments_get,
    newsletter_send,
    posts_delete,
    posts_send,
    posts_update,
    reply_send,
    task_complete,
)

# Ordine identico a v1 main.py, + newsletter_send (v2, non ha equivalente v1).
TASKS = [
    ("posts_send", posts_send.run),
    ("newsletter_send", newsletter_send.run),
    ("comments_get", comments_get.run),
    ("reply_send", reply_send.run),
    ("task_complete", task_complete.run),
    ("posts_update", posts_update.run),
    ("posts_delete", posts_delete.run),
]


def main() -> None:
    # stdout, non lo stderr di default: banner (print) e log condividono lo
    # stesso stream, altrimenti l'ordine si mischia quando entrambi finiscono
    # nello stesso file/terminale (buffering separato tra i due stream).
    handler = logging.StreamHandler(sys.stdout)
    # Indentate: le righe di log leggono come "dentro" il blocco aperto da
    # cli_output.task_start(), senza dover far combaciare larghezze di bordi.
    fmt = cli_output.LOG_INDENT + "%(asctime)s %(levelname)s %(name)s: %(message)s"
    handler.setFormatter(cli_output.ColorFormatter(fmt))
    logging.basicConfig(level=logging.DEBUG if config.DEBUG else logging.INFO, handlers=[handler])
    log = logging.getLogger("publisher")
    log.info("worker start (dry_run=%s)", config.DRY_RUN)

    with connection() as conn, run_lock(conn) as acquired:
        if not acquired:
            log.warning("worker: run precedente ancora in corso, esco senza fare nulla")
            return
        for name, run in TASKS:
            started = cli_output.task_start(name)
            try:
                run(conn)
                checkpoint(conn)
            except Exception:  # noqa: BLE001 — un task non deve far cadere gli altri
                # Scarta solo il lavoro non ancora committato del task fallito
                # (le azioni remote gia' riuscite sono state committate dai
                # checkpoint interni) e rimette la connessione in stato pulito.
                conn.rollback()
                log.exception("task '%s' fallito", name)
            cli_output.task_end(name, started)

    log.info("worker end")


if __name__ == "__main__":
    main()
