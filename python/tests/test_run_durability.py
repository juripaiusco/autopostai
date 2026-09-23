"""Durabilita' del run del worker: commit ai checkpoint + un solo run alla volta.

Regressione: l'intero run era un'unica transazione committata alla fine, senza
lock tra run. Un run interrotto perdeva gli id remoti gia' pubblicati (il run
dopo ripubblicava) e due run sovrapposti pubblicavano due volte lo stesso post.
"""

import pytest

from publisher import config
from publisher.db.engine import checkpoint, get_engine, run_lock


class RecordingConn:
    def __init__(self, options):
        self.options = options
        self.commits = 0

    def get_execution_options(self):
        return self.options

    def commit(self):
        self.commits += 1


def test_checkpoint_commits_only_worker_connections():
    worker_conn = RecordingConn({"publisher_commit_points": True})
    test_conn = RecordingConn({})

    checkpoint(worker_conn)
    checkpoint(test_conn)

    assert worker_conn.commits == 1
    assert test_conn.commits == 0  # i test DB fanno rollback: mai committare a meta'


class FakeWorkerConn(RecordingConn):
    def __init__(self):
        super().__init__({"publisher_commit_points": True})
        self.log = []

    def commit(self):
        self.log.append("commit")

    def rollback(self):
        self.log.append("rollback")


def _run_worker(monkeypatch, tasks, lock_free=True):
    from contextlib import contextmanager

    from publisher import worker

    conn = FakeWorkerConn()

    @contextmanager
    def fake_connection():
        yield conn

    @contextmanager
    def fake_lock(_conn):
        yield lock_free

    monkeypatch.setattr(worker, "connection", fake_connection)
    monkeypatch.setattr(worker, "run_lock", fake_lock)
    monkeypatch.setattr(worker, "TASKS", tasks)
    worker.main()
    return conn


def test_worker_commits_after_each_task_and_rolls_back_only_the_failed_one(monkeypatch):
    def ok(conn):
        conn.log.append("ok")

    def boom(conn):
        conn.log.append("boom")
        raise RuntimeError("task fallito")

    conn = _run_worker(monkeypatch, [("a", ok), ("b", boom), ("c", ok)])

    assert conn.log == ["ok", "commit", "boom", "rollback", "ok", "commit"]


def test_worker_exits_when_another_run_holds_the_lock(monkeypatch):
    def must_not_run(conn):
        raise AssertionError("nessun task deve girare con il lock occupato")

    conn = _run_worker(monkeypatch, [("a", must_not_run)], lock_free=False)

    assert conn.log == []


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1 e il DB di sviluppo")
def test_run_lock_allows_a_single_run():
    engine = get_engine()
    with engine.connect() as first, engine.connect() as second:
        with run_lock(first) as first_acquired:
            assert first_acquired is True
            with run_lock(second) as second_acquired:
                assert second_acquired is False  # run sovrapposto: esce senza fare nulla
        # rilasciato all'uscita del primo run
        with run_lock(second) as acquired_after:
            assert acquired_after is True
