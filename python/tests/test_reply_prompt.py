"""Unit test: la risposta AI a un commento riceve il contesto del post, come v1.

Regressione: l'LLM riceveva solo foto + commento ("Mario ha risposto: ...") senza
sapere che era il proprio post, e rispondeva "non posso riconoscere le persone in
foto". Ora il contesto e' prompt del post (+ foto) -> testo pubblicato -> commento.
Niente DB, niente rete, niente LLM.
"""

import pytest

from publisher import config
from publisher.content import ContentService
from publisher.domain import media
from publisher.integrations import openai_text


class FakeTokenRepo:
    def log(self, user_id, ref_type, reference_id, tokens, now):
        pass


@pytest.fixture
def calls(monkeypatch):
    sent = []

    def fake_generate(**kwargs):
        sent.append(kwargs)
        return "Grazie Mario!", 1

    monkeypatch.setattr(config, "DRY_RUN", False)
    monkeypatch.setattr(openai_text, "generate", fake_generate)
    return sent


def _comment(**overrides):
    comment = {
        "id": 5, "post_id": 11, "user_id": 2, "message": "Che bella foto!",
        "ai_prompt_post": "Parla della pizza", "ai_content": "La nostra pizza del giorno",
        "img": None, "img_ai_check_on": "0", "openai_api_key": "sk-fake",
    }
    comment.update(overrides)
    return comment


def _reply(comment):
    ContentService(None, FakeTokenRepo()).generate_reply(comment, "Mario ha risposto: Che bella foto!")


def test_reply_gets_post_prompt_and_published_text_as_context(calls):
    _reply(_comment())

    assert calls[0]["context"] == [
        {"role": "user", "content": "Parla della pizza"},
        {"role": "assistant", "content": "La nostra pizza del giorno"},
    ]
    assert calls[0]["user_prompt"] == "Mario ha risposto: Che bella foto! Rispondi al commento."
    assert "img_path" not in calls[0]


def test_post_image_goes_in_the_post_message_not_the_comment(calls, monkeypatch):
    monkeypatch.setattr(media, "has_images", lambda post: True)
    monkeypatch.setattr(media, "local_paths", lambda post: ["/tmp/pizza.jpg"])
    monkeypatch.setattr(openai_text, "_to_base64", lambda path: "QUJD")

    _reply(_comment(img="pizza.jpg", img_ai_check_on="1"))

    post_message = calls[0]["context"][0]["content"]
    assert post_message[0] == {"type": "text", "text": "Parla della pizza"}
    assert post_message[1]["image_url"]["url"] == "data:image/jpeg;base64,QUJD"
    assert isinstance(calls[0]["user_prompt"], str)


def test_missing_prompt_and_content_are_omitted(calls):
    _reply(_comment(ai_prompt_post=None, ai_content="  "))

    assert calls[0]["context"] == []


def test_openai_messages_order_system_context_user(monkeypatch):
    captured = {}

    class FakeCompletions:
        def create(self, **kwargs):
            captured.update(kwargs)
            raise RuntimeError("stop")  # basta verificare i messaggi

    class FakeOpenAI:
        def __init__(self, **kwargs):
            self.chat = type("Chat", (), {"completions": FakeCompletions()})()

    monkeypatch.setattr(openai_text, "OpenAI", FakeOpenAI)
    with pytest.raises(RuntimeError):
        openai_text.generate("sk-test", "sys", "rispondi", context=[{"role": "assistant", "content": "post"}])

    assert captured["messages"] == [
        {"role": "system", "content": "sys"},
        {"role": "assistant", "content": "post"},
        {"role": "user", "content": "rispondi"},
    ]
