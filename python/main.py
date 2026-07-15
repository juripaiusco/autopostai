import base64

from dotenv import load_dotenv
from fastapi import FastAPI
from fastapi.responses import JSONResponse
from pydantic import BaseModel

from providers import ImageGenerationError, provider_for_model

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


@app.get("/health")
def health():
    return {"status": "ok"}
