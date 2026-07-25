import base64

from dotenv import load_dotenv
from fastapi import FastAPI
from fastapi.responses import JSONResponse
from openai import OpenAIError
from pydantic import BaseModel

from providers import ImageGenerationError, provider_for_model
from publisher.publishing import PUBLISHERS
from publisher.integrations import openai_text

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
    ai_prompt_post: str
    channel: str | None = None
    ai_personality: str | None = None
    ai_prompt_prefix: str | None = None
    model: str | None = None


@app.post("/generate-text")
def generate_text(data: GenerateTextRequest):
    publisher_cls = PUBLISHERS.get(data.channel) if data.channel else None
    user_prompt = (
        publisher_cls({"ai_prompt_post": data.ai_prompt_post}).build_prompt()
        if publisher_cls
        else data.ai_prompt_post
    )
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

    return {"content": text, "tokens": tokens}


@app.get("/health")
def health():
    return {"status": "ok"}
