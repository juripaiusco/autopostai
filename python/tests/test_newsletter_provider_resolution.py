"""Unit test: il provider newsletter si risolve dal canale, non dalle chiavi settings.

Regressione: posts_delete/task_complete guardavano quale chiave API era
valorizzata nei settings dell'account. Un post smtp_custom non veniva mai marcato
deleted=1 (nessun ramo -> id_del=None), e con una chiave Mailchimp/Brevo presente
sull'account partivano chiamate verso il provider sbagliato ad ogni run.
Niente DB: repository e client esterni sono sostituiti da finti.
"""

import pytest

from publisher import config
from publisher.domain.channels import Channels
from publisher.tasks import posts_delete, task_complete

ALL_KEYS = {"nl_mailchimp_api": "mc-key", "nl_mailchimp_datacenter": "us1", "nl_brevo_api": "brevo-key"}


def _newsletter(provider: str | None, remote_id: str = "nl-1") -> dict:
    data = {"on": True, "id": remote_id, "url": None}
    if provider:
        data["provider"] = provider
    return {"newsletter": data}


class FakePostRepo:
    def __init__(self, posts):
        self.posts = posts
        self.saved = {}
        self.deleted = {}

    def due_for_deletion(self, now):
        return self.posts

    def save_channels(self, post_id, channels):
        self.saved[post_id] = channels

    def set_deleted(self, post_id, value):
        self.deleted[post_id] = value


class ExplodingClient:
    """Qualsiasi istanza fa fallire il test: il provider non doveva essere chiamato."""

    def __init__(self, *args, **kwargs):
        raise AssertionError(f"{type(self).__name__} non doveva essere istanziato")


@pytest.fixture
def live(monkeypatch):
    monkeypatch.setattr(config, "DRY_RUN", False)


def _run_delete(monkeypatch, post):
    repo = FakePostRepo([post])
    monkeypatch.setattr(posts_delete, "PostRepository", lambda conn: repo)
    posts_delete.run(conn=None)
    return repo


# --- ChannelEntry.resolve_newsletter_provider ---

def test_explicit_provider_wins_over_settings_keys():
    entry = Channels.parse(_newsletter("smtp_custom")).entry("newsletter")
    assert entry.resolve_newsletter_provider(ALL_KEYS) == "smtp_custom"


def test_fallback_on_settings_keys_for_rows_without_provider():
    entry = Channels.parse(_newsletter(None)).entry("newsletter")
    assert entry.resolve_newsletter_provider(ALL_KEYS) == "mailchimp"
    assert entry.resolve_newsletter_provider({"nl_brevo_api": "k"}) == "brevo"
    assert entry.resolve_newsletter_provider({}) is None


# --- posts_delete ---

def test_delete_smtp_custom_marks_deleted_without_remote_calls(monkeypatch, live):
    monkeypatch.setattr(posts_delete, "Mailchimp", ExplodingClient)
    post = {"id": 14, "channels": _newsletter("smtp_custom", "smtp-14"), **ALL_KEYS}

    repo = _run_delete(monkeypatch, post)

    assert repo.saved[14]["newsletter"]["id_del"] == "smtp-14"
    assert repo.deleted[14] == "1"


def test_delete_brevo_copies_id_even_if_account_has_mailchimp_key(monkeypatch, live):
    monkeypatch.setattr(posts_delete, "Mailchimp", ExplodingClient)
    post = {"id": 15, "channels": _newsletter("brevo", "123"), **ALL_KEYS}

    repo = _run_delete(monkeypatch, post)

    assert repo.saved[15]["newsletter"]["id_del"] == "123"
    assert repo.deleted[15] == "1"


def test_delete_mailchimp_calls_mailchimp(monkeypatch, live):
    calls = []

    class FakeMailchimp:
        def __init__(self, api_key, datacenter):
            assert (api_key, datacenter) == ("mc-key", "us1")

        def delete(self, remote_id):
            calls.append(remote_id)
            return remote_id

    monkeypatch.setattr(posts_delete, "Mailchimp", FakeMailchimp)
    post = {"id": 16, "channels": _newsletter("mailchimp", "camp-1"), **ALL_KEYS}

    repo = _run_delete(monkeypatch, post)

    assert calls == ["camp-1"]
    assert repo.deleted[16] == "1"


# --- task_complete ---

@pytest.mark.parametrize("provider", ["smtp_custom", "mailchimp"])
def test_task_complete_asks_brevo_only_for_brevo_posts(monkeypatch, provider):
    monkeypatch.setattr(task_complete, "Brevo", ExplodingClient)
    entry = Channels.parse(_newsletter(provider)).entry("newsletter")

    assert task_complete._newsletter_complete(entry, {"id": 1, **ALL_KEYS}) is True


def test_task_complete_fetches_brevo_share_link(monkeypatch):
    class FakeBrevo:
        def __init__(self, api_key):
            pass

        def share_link(self, remote_id):
            return f"https://brevo.example/{remote_id}"

    monkeypatch.setattr(task_complete, "Brevo", FakeBrevo)
    entry = Channels.parse(_newsletter("brevo", "77")).entry("newsletter")

    assert task_complete._newsletter_complete(entry, {"id": 1, **ALL_KEYS}) is True
    assert entry.data["url"] == "https://brevo.example/77"
