from .base import ImageGenerationError, ImageProvider
from .openai_provider import OpenAIImageProvider

PROVIDERS_BY_MODEL = {
    "gpt-image-1": OpenAIImageProvider,
}


def provider_for_model(model: str, api_key: str) -> ImageProvider:
    provider_cls = PROVIDERS_BY_MODEL.get(model)
    if provider_cls is None:
        raise ImageGenerationError(f"Modello non supportato: {model}")
    return provider_cls(api_key)
