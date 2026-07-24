"""Entry point del worker di pubblicazione (cron one-shot).

Come v1: viene lanciato una volta (ogni minuto, dal servizio Docker `publisher`),
esegue i task in ordine su UNA sola connessione/transazione e termina. Nessun loop
interno: la cadenza la da' l'orchestratore esterno (Docker/cron).

    python -m publisher.worker

I task sono eseguiti in ordine; ognuno isola i propri errori cosi' che il fallimento
di uno non impedisca agli altri di girare. Solo `posts_send` e' implementato: gli
altri sono scheletri no-op finche' non verranno portati.
"""

from __future__ import annotations

import logging
import sys

from publisher import cli_output, config
from publisher.db.engine import connection
from publisher.tasks import (
    comments_get,
    posts_delete,
    posts_send,
    posts_update,
    reply_send,
    task_complete,
)

# Ordine identico a v1 main.py.
TASKS = [
    ("posts_send", posts_send.run),
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

    with connection() as conn:
        for name, run in TASKS:
            started = cli_output.task_start(name)
            try:
                run(conn)
            except Exception:  # noqa: BLE001 — un task non deve far cadere gli altri
                log.exception("task '%s' fallito", name)
            cli_output.task_end(name, started)

    log.info("worker end")


if __name__ == "__main__":
    main()
