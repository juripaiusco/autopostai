"""Generazione testo via LLM (OpenAI).

Ottimizzazione vs v1: usa il **SDK ufficiale `openai`** (gia' dipendenza del
servizio immagini) invece delle chiamate `requests` grezze in `services/gpt.py`,
unificando i due stili di accesso a OpenAI che coesistevano in v1. Chiave BYOK
per-account passata dal chiamante, mai una globale.
"""

from __future__ import annotations

import base64

from openai import OpenAI

from publisher import config


def generate(
    api_key: str,
    system_prompt: str,
    user_prompt: str,
    img_path: str | None = None,
    model: str | None = None,
    context: list[dict] | None = None,
) -> tuple[str, int]:
    """Ritorna (testo, token_totali_usati).

    `context`: messaggi (user/assistant) inseriti tra il system e lo user finale,
    per dare all'LLM la conversazione precedente (es. post pubblicato prima di
    rispondere a un commento, come la catena `messages` di v1)."""
    client = OpenAI(
        api_key=api_key,
        base_url=config.OPENAI_BASE_URL,
        timeout=config.OPENAI_TIMEOUT,
        max_retries=config.OPENAI_MAX_RETRIES,
    )

    response = client.chat.completions.create(
        model=model or config.OPENAI_MODEL,
        messages=[
            {"role": "system", "content": system_prompt},
            *(context or []),
            {"role": "user", "content": user_content(user_prompt, img_path)},
        ],
        temperature=1.0,
    )

    text = response.choices[0].message.content or ""
    tokens = response.usage.total_tokens if response.usage else 0
    return text, tokens


def user_content(text: str, img_path: str | None = None) -> object:
    """Contenuto di un messaggio user: solo testo, o testo + immagine in base64."""
    if not img_path:
        return text
    return [
        {"type": "text", "text": text},
        {
            "type": "image_url",
            "image_url": {"url": f"data:image/jpeg;base64,{_to_base64(img_path)}"},
        },
    ]


def _to_base64(img_path: str) -> str:
    with open(img_path, "rb") as f:
        return base64.b64encode(f.read()).decode("utf-8")
