import base64

from openai import OpenAI, OpenAIError

from .base import ImageGenerationError, ImageProvider


class OpenAIImageProvider(ImageProvider):
    def __init__(self, api_key: str) -> None:
        if not api_key:
            raise ImageGenerationError("Nessuna chiave OpenAI collegata per questo account")
        self._client = OpenAI(api_key=api_key)

    def generate(self, prompt: str) -> bytes:
        try:
            response = self._client.images.generate(
                model="gpt-image-1",
                prompt=prompt,
                size="1024x1024",
                quality="medium",
                n=1,
            )
        except OpenAIError as exc:
            raise ImageGenerationError(str(exc)) from exc

        b64 = response.data[0].b64_json
        if not b64:
            raise ImageGenerationError("Risposta OpenAI senza immagine")

        return base64.b64decode(b64)
