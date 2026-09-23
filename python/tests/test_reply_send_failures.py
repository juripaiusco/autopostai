"""Unit test: una risposta automatica fallita non viene ritentata all'infinito.

Regressione: su errore del canale (o nessun id restituito) reply_send faceva
`return` senza segnare nulla: lo stesso commento tornava primo in coda ad ogni
run, la risposta AI veniva rigenerata e i token addebitati all'account ogni
minuto. Ora il commento riceve reply_failed_at ed esce dalla coda.
Niente DB, niente rete, niente LLM.
"""

import pytest

from publisher import config
from publisher.tasks import reply_send

COMMENT = {"id": 7, "channel": "facebook", "channels": {"facebook": {"on": True, "auto_reply_enabled": True}}}


class FakeCommentRepo:
    def __init__(self, comments):
        self.comments = comments
        self.failed, self.replied = [], []

    def due_for_reply(self, limit):
        return self.comments

    def mark_reply_failed(self, comment_id, now):
        self.failed.append(comment_id)

    def mark_replied(self, comment_id, reply_id, reply_text, now):
        self.replied.append((comment_id, reply_id))


class FakeContentService:
    def __init__(self, *args):
        self.generated = 0

    def generate_reply(self, comment, prompt):
        self.generated += 1
        return "Grazie del commento!"


class FakeResult:
    def __init__(self, remote_id):
        self.remote_id = remote_id
        self.text = "Grazie del commento!"


def _replier(outcome):
    class FakeReplier:
        def __init__(self, comment):
            pass

        def build_prompt(self):
            return "prompt"

        def send(self, text):
            if isinstance(outcome, Exception):
                raise outcome
            return FakeResult(outcome)

    return FakeReplier


def _run(monkeypatch, outcome):
    repo = FakeCommentRepo([dict(COMMENT)])
    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(reply_send, "CommentRepository", lambda conn: repo)
    monkeypatch.setattr(reply_send, "PostRepository", lambda conn: None)
    monkeypatch.setattr(reply_send, "TokenLogRepository", lambda conn: None)
    monkeypatch.setattr(reply_send, "ContentService", FakeContentService)
    monkeypatch.setattr(reply_send, "REPLIERS", {"facebook": _replier(outcome)})
    reply_send.run(conn=None)
    return repo


@pytest.mark.parametrize("outcome", [RuntimeError("commento eliminato"), None])
def test_failed_reply_is_marked_and_leaves_the_queue(monkeypatch, outcome):
    repo = _run(monkeypatch, outcome)
    assert repo.failed == [7]
    assert repo.replied == []


def test_successful_reply_is_saved(monkeypatch):
    repo = _run(monkeypatch, "reply-123")
    assert repo.replied == [(7, "reply-123")]
    assert repo.failed == []
