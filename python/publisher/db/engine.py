"""Accesso al DB via SQLAlchemy Core.

Ottimizzazione rispetto a v1 (che apriva/chiudeva una connessione mysql.connector
a quasi ogni funzione, anche piu' volte per task): qui si apre **una sola
connessione per run** del worker e la si passa ai repository. Le tabelle restano
di proprieta' di Laravel — non usiamo l'ORM, solo Core con query parametrizzate.

Durabilita': NON una transazione unica per run. Un'azione remota (post
pubblicato, email spedita) seguita da una scrittura DB va committata subito
(`checkpoint`), altrimenti un run interrotto o un errore di commit perdeva gli
id remoti e il run dopo ripubblicava/re-inviava.
"""

from collections.abc import Iterator
from contextlib import contextmanager

from sqlalchemy import create_engine, text
from sqlalchemy.engine import Connection, Engine

from publisher import config

_engine: Engine | None = None


def get_engine() -> Engine:
    """Engine singleton, creato pigramente al primo uso."""
    global _engine
    if _engine is None:
        url = (
            f"mysql+pymysql://{config.DB_USERNAME}:{config.DB_PASSWORD}"
            f"@{config.DB_HOST}:{config.DB_PORT}/{config.DB_DATABASE}?charset=utf8mb4"
        )
        # pool_pre_ping evita connessioni "stantie" tra un run e l'altro.
        _engine = create_engine(url, pool_pre_ping=True, future=True)
    return _engine


# Execution option per-Connection (non finisce nel pool, a differenza di conn.info).
_COMMIT_POINTS = "publisher_commit_points"


@contextmanager
def connection() -> Iterator[Connection]:
    """Connessione per l'intero run del worker, con commit ad ogni checkpoint().

    Usare come `with connection() as conn:` — commit finale all'uscita pulita,
    rollback del lavoro non ancora committato in caso di eccezione.
    """
    engine = get_engine()
    with engine.connect() as conn:
        conn.execution_options(**{_COMMIT_POINTS: True})
        try:
            yield conn
            conn.commit()
        except BaseException:
            conn.rollback()
            raise


def checkpoint(conn: Connection) -> None:
    """Rende durevole il lavoro fatto finora sul run. Da chiamare subito dopo
    aver salvato l'esito di un'azione remota non ripetibile.

    No-op sulle connessioni non aperte da connection(): i test DB passano una
    connessione con una transazione esterna che fanno rollback a fine test, e
    non deve essere committata a meta'."""
    if conn.get_execution_options().get(_COMMIT_POINTS):
        conn.commit()


@contextmanager
def run_lock(conn: Connection) -> Iterator[bool]:
    """Lock applicativo MariaDB: True se questo run e' l'unico in corso.

    Il cron lancia un run al minuto, ma un run puo' durare di piu' (LLM, SMTP,
    API lente): due run sovrapposti pubblicavano due volte lo stesso post.
    GET_LOCK e' legato alla sessione, non alla transazione (i commit non lo
    rilasciano) e il server lo libera da solo se il processo muore. Il nome
    include il database: beta e produzione sullo stesso server non si bloccano.
    """
    name = f"{config.DB_DATABASE}:publisher"
    acquired = conn.execute(text("SELECT GET_LOCK(:name, 0)"), {"name": name}).scalar() == 1
    try:
        yield acquired
    finally:
        if acquired:
            conn.execute(text("SELECT RELEASE_LOCK(:name)"), {"name": name})
