"""Unit test: solo un rifiuto del destinatario e' un bounce.

Regressione: qualsiasi eccezione SMTP (host sbagliato, password errata, timeout)
marcava il contatto `bounced` per sempre — un account SMTP mal configurato
svuotava la lista contatti al ritmo di un batch al minuto. Niente DB ne' rete.
"""

import smtplib

import pytest

from publisher import config
from publisher.integrations.smtp import RecipientRefused, SmtpClient
from publisher.tasks import newsletter_send


# --- SmtpClient: mappatura SMTPRecipientsRefused -> RecipientRefused ---

class FakeServer:
    def __init__(self, error):
        self.error = error

    def login(self, username, password):
        pass

    def sendmail(self, from_address, to, message):
        raise self.error


def _send_through(error):
    client = SmtpClient("smtp.test.it", 587, "user", "pw", "tls")
    client._login_and_send(FakeServer(error), "news@example.com", "to@example.com", _message())


def _message():
    from email.mime.text import MIMEText
    return MIMEText("<p>x</p>", "html")


@pytest.mark.parametrize("code,permanent", [(550, True), (553, True), (450, False), (452, False)])
def test_recipient_refused_is_classified_by_code(code, permanent):
    with pytest.raises(RecipientRefused) as exc:
        _send_through(smtplib.SMTPRecipientsRefused({"to@example.com": (code, b"mailbox problem")}))
    assert exc.value.permanent is permanent
    assert str(code) in str(exc.value)


def test_account_errors_are_not_recipient_refused():
    with pytest.raises(smtplib.SMTPAuthenticationError):
        _send_through(smtplib.SMTPAuthenticationError(535, b"bad credentials"))


# --- newsletter_send._send_batch ---

class FakeConn:
    """Connessione fuori da engine.connection(): checkpoint() e' un no-op."""

    def get_execution_options(self):
        return {}


class FakeContactRepo:
    def __init__(self):
        self.conn = FakeConn()
        self.next_id = 0
        self.placeholders = {}
        self.sent, self.deleted = [], []
        self.bounced = []  # (contact_id, permanent)

    def create_placeholder(self, contact_id, post_id, now):
        self.next_id += 1
        self.placeholders[self.next_id] = contact_id
        return self.next_id

    def mark_sent(self, send_id, now):
        self.sent.append(self.placeholders[send_id])

    def mark_send_bounced(self, send_id, contact_id, now, error_message, permanent=True):
        self.bounced.append((contact_id, permanent))

    def delete_placeholder(self, send_id):
        self.deleted.append(self.placeholders.pop(send_id))


class FakeClient:
    """errors: {email: eccezione da sollevare}. Registra i tentativi."""

    def __init__(self, errors):
        self.errors = errors
        self.attempts = []

    def send(self, to_email, *args):
        self.attempts.append(to_email)
        if to_email in self.errors:
            raise self.errors[to_email]


CONTACTS = [{"id": i, "email": f"c{i}@example.com"} for i in (1, 2, 3)]
POST = {"id": 9, "nl_smtp_sender": "News <news@example.com>", "nl_smtp_username": "u", "nl_smtp_host": "smtp.test.it"}


@pytest.fixture(autouse=True)
def live(monkeypatch):
    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(newsletter_send, "_finalize_html", lambda html, contact_id, send_id: html)


def _run(errors):
    repo, client = FakeContactRepo(), FakeClient(errors)
    result = newsletter_send._send_batch(client, CONTACTS, POST, "Oggetto", "<p>x</p>", repo, "2026-09-23 10:00:00")
    return result, repo, client


def test_hard_bounce_marks_contact_and_continues():
    (sent, bounced), repo, _ = _run({"c2@example.com": RecipientRefused(550, "no such user")})
    assert (sent, bounced) == (2, 1)
    assert repo.sent == [1, 3]
    assert repo.bounced == [(2, True)]


def test_soft_bounce_keeps_contact_active():
    (_, bounced), repo, _ = _run({"c2@example.com": RecipientRefused(452, "mailbox full")})
    assert bounced == 1
    assert repo.bounced == [(2, False)]


@pytest.mark.parametrize("error", [
    ConnectionRefusedError("connection refused"),
    smtplib.SMTPAuthenticationError(535, b"bad credentials"),
    smtplib.SMTPSenderRefused(553, b"sender not allowed", "news@example.com"),
    TimeoutError("timed out"),
])
def test_account_error_stops_batch_without_bouncing(error):
    (sent, bounced), repo, client = _run({"c1@example.com": error})
    assert (sent, bounced) == (0, 0)
    assert repo.bounced == []
    assert repo.deleted == [1]       # placeholder rimosso: riprovato al giro dopo
    assert client.attempts == ["c1@example.com"]  # gli altri non vengono tentati


def test_account_error_mid_batch_keeps_already_sent():
    (sent, bounced), repo, client = _run({"c2@example.com": ConnectionResetError("reset")})
    assert (sent, bounced) == (1, 0)
    assert repo.sent == [1]
    assert repo.deleted == [2]
    assert "c3@example.com" not in client.attempts
