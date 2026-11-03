"""Unit test: il prompt del testo AI generato dal worker dipende dal canale
primario del post (WordPress > Newsletter > base), non dal primo canale del ciclo.

Regressione: un post Facebook + WordPress senza anteprima veniva generato col
prompt di Facebook (primo in ordine) e l'articolo WordPress usciva senza titolo
Markdown; "Genera anteprima" e il job Laravel usavano invece il prompt WordPress.
Niente DB, niente rete, niente LLM.
"""

import pytest

from publisher import config
from publisher.content import ContentService
from publisher.integrations import openai_text
from publisher.publishing.facebook import FacebookPublisher


class FakePostRepo:
    def save_ai_content(self, post_id, content):
        pass


class FakeTokenRepo:
    def log(self, user_id, ref_type, reference_id, tokens, now):
        pass


@pytest.fixture
def prompts(monkeypatch):
    sent = []

    def fake_generate(**kwargs):
        sent.append(kwargs["user_prompt"])
        return "Testo", 1

    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(openai_text, "generate", fake_generate)
    return sent


def _resolve_on_facebook(channels):
    post = {
        "id": 11, "user_id": 2, "ai_prompt_post": "Parla della pizza", "ai_content": None,
        "img": None, "img_ai_check_on": "0", "openai_api_key": "sk-fake", "channels": channels,
    }
    ContentService(FakePostRepo(), FakeTokenRepo()).resolve(post, FacebookPublisher(post), lambda *a: "")


def test_facebook_plus_wordpress_uses_the_wordpress_prompt(prompts):
    _resolve_on_facebook({"facebook": {"on": True}, "wordpress": {"on": True}})

    assert prompts[0].startswith("Generami un articolo per wordpress")
    assert prompts[0].endswith("Parla della pizza")


def test_social_only_uses_the_plain_prompt(prompts):
    _resolve_on_facebook({"facebook": {"on": True}, "instagram": {"on": True}})

    assert prompts == ["Parla della pizza"]
