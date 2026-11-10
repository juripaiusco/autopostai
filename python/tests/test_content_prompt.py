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


def test_linkedin_on_adds_the_character_limit_to_the_prompt(prompts):
    _resolve_on_facebook({"facebook": {"on": True}, "linkedin": {"on": True}})

    assert prompts[0].startswith("Parla della pizza ")
    assert "LinkedIn" in prompts[0] and "3000" in prompts[0]


def test_linkedin_off_leaves_the_prompt_untouched(prompts):
    _resolve_on_facebook({"facebook": {"on": True}, "linkedin": {"on": False}})

    assert prompts == ["Parla della pizza"]


def test_generate_text_endpoint_adds_the_limit_for_linkedin(monkeypatch):
    from fastapi.testclient import TestClient

    import main

    sent = []

    def fake_generate(**kwargs):
        sent.append(kwargs["user_prompt"])
        return "Testo", 1

    monkeypatch.setattr(openai_text, "generate", fake_generate)
    client = TestClient(main.app)

    base = {"api_key": "sk-fake", "ai_prompt_post": "Parla della pizza"}
    assert client.post("/generate-text", json=base).status_code == 200
    assert client.post("/generate-text", json={**base, "channels": ["facebook", "linkedin"]}).status_code == 200

    assert sent[0] == "Parla della pizza"
    assert sent[1].startswith("Parla della pizza ") and "LinkedIn" in sent[1]


def test_generate_text_endpoint_rewrites_an_article_for_social(monkeypatch):
    from fastapi.testclient import TestClient

    import main

    sent = []

    def fake_generate(**kwargs):
        sent.append(kwargs["user_prompt"])
        return "Post social", 1

    monkeypatch.setattr(openai_text, "generate", fake_generate)
    client = TestClient(main.app)

    response = client.post("/generate-text", json={
        "api_key": "sk-fake",
        "ai_prompt_post": "Tono allegro",
        "channel": "wordpress",
        "channels": ["wordpress", "linkedin"],
        "source_text": "Novità di novembre\n\nArrivano i nuovi prodotti.",
    })

    assert response.status_code == 200 and response.json()["content"] == "Post social"
    prompt = sent[0]
    assert prompt.startswith("Riscrivi questo articolo come post per i social")
    assert "niente Markdown" in prompt and "Generami un articolo" not in prompt
    assert "Istruzioni originali del post: Tono allegro" in prompt
    assert "Articolo:\nNovità di novembre" in prompt
    assert "LinkedIn" in prompt.split("Articolo:")[1]


def test_generate_text_endpoint_social_rewrite_without_prompt(monkeypatch):
    from fastapi.testclient import TestClient

    import main

    sent = []
    monkeypatch.setattr(openai_text, "generate", lambda **kw: (sent.append(kw["user_prompt"]) or ("ok", 1)))

    response = TestClient(main.app).post("/generate-text", json={"api_key": "sk-fake", "source_text": "Articolo"})

    assert response.status_code == 200
    assert "Istruzioni originali" not in sent[0]
