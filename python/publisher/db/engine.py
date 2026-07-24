"""Accesso al DB via SQLAlchemy Core.

Ottimizzazione rispetto a v1 (che apriva/chiudeva una connessione mysql.connector
a quasi ogni funzione, anche piu' volte per task): qui si apre **una sola
connessione per run** del worker e la si passa ai repository. Le tabelle restano
di proprieta' di Laravel — non usiamo l'ORM, solo Core con query parametrizzate.
"""

from contextlib import contextmanager

from sqlalchemy import create_engine
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


@contextmanager
def connection() -> Connection:
    """Una connessione con transazione per l'intero run del worker.

    Usare come `with connection() as conn:` — il commit avviene all'uscita pulita,
    il rollback in caso di eccezione (`Connection.begin()`).
    """
    engine = get_engine()
    with engine.begin() as conn:
        yield conn
