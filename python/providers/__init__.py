from .base import ImageGenerationError, ImageProvider
from .openai_provider import OpenAIImageProvider

# Allineato a config/ai.php (image_models) lato Laravel.
PROVIDERS_BY_MODEL = {
    "gpt-image-2.5-flare": OpenAIImageProvider,
    "gpt-image-2.5-sunburst": OpenAIImageProvider,
}


def provider_for_model(model: str, api_key: str) -> ImageProvider:
    provider_cls = PROVIDERS_BY_MODEL.get(model)
    if provider_cls is None:
        raise ImageGenerationError(f"Modello non supportato: {model}")
    return provider_cls(api_key, model)
