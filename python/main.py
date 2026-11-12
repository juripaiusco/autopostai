import base64

from dotenv import load_dotenv
from fastapi import FastAPI
from fastapi.responses import JSONResponse
from openai import OpenAIError
from pydantic import BaseModel

from providers import ImageGenerationError, provider_for_model
from publisher.domain.channel_text import social_rewrite_prompt
from publisher.domain.channels import with_length_instruction
from publisher.publishing import PUBLISHERS
from publisher.integrations import openai_text, web_pages

load_dotenv()

app = FastAPI(title="autopostai image service")


class GenerateImageRequest(BaseModel):
    prompt: str
    model: str
    api_key: str


@app.post("/generate-image")
def generate_image(data: GenerateImageRequest):
    try:
        provider = provider_for_model(data.model, data.api_key)
        image_bytes = provider.generate(data.prompt)
    except ImageGenerationError as exc:
        return JSONResponse(status_code=422, content={"detail": str(exc)})

    return {
        "image_base64": base64.b64encode(image_bytes).decode("ascii"),
        "mime_type": "image/png",
    }


class GenerateTextRequest(BaseModel):
    api_key: str
    ai_prompt_post: str = ""
    channel: str | None = None
    # Canali accesi sul post: servono per il limite di caratteri (LinkedIn).
    channels: list[str] | None = None
    # "Genera versione social": articolo da riscrivere come post social.
    source_text: str | None = None
    ai_personality: str | None = None
    ai_prompt_prefix: str | None = None
    model: str | None = None


@app.post("/generate-text")
def generate_text(data: GenerateTextRequest):
    publisher_cls = PUBLISHERS.get(data.channel) if data.channel else None
    # Newsletter/WordPress: contenuto delle pagine linkate nel prompt (web_pages).
    enrichment = None
    if (data.source_text or "").strip():
        user_prompt = social_rewrite_prompt(data.source_text, data.ai_prompt_post)
    elif publisher_cls:
        user_prompt = publisher_cls({"ai_prompt_post": data.ai_prompt_post}).build_prompt()
        if publisher_cls.reads_urls:
            enrichment = web_pages.enrich(user_prompt)
            user_prompt = enrichment.prompt
    else:
        user_prompt = data.ai_prompt_post
    user_prompt = with_length_instruction(user_prompt, data.channels or [])
    system_prompt = " ".join(
        filter(
            None,
            [
                data.ai_personality,
                data.ai_prompt_prefix,
                "Rispondi sempre solo con l'output richiesto, senza aggiungere altro.",
            ],
        )
    )

    try:
        text, tokens = openai_text.generate(
            api_key=data.api_key,
            system_prompt=system_prompt,
            user_prompt=user_prompt,
            model=data.model,
        )
    except OpenAIError as exc:
        return JSONResponse(status_code=422, content={"detail": str(exc)})

    warnings = []
    if enrichment:
        text, removed = web_pages.keep_known_images(text, enrichment.image_paths)
        warnings = list(enrichment.warnings)
        if removed:
            warnings.append(web_pages.removed_images_warning(removed))

    if not text.strip():
        return JSONResponse(status_code=422, content={"detail": "L'AI ha restituito un testo vuoto, riprova."})

    return {"content": text, "tokens": tokens, "warnings": warnings}


@app.get("/health")
def health():
    return {"status": "ok"}
