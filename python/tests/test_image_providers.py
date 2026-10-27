"""Unit test della scelta del provider immagini per modello (nessuna chiamata reale a OpenAI)."""

import base64
from types import SimpleNamespace
from unittest.mock import MagicMock

import pytest

from providers import ImageGenerationError, OpenAIImageProvider, provider_for_model


@pytest.mark.parametrize("model", ["gpt-image-2.5-flare", "gpt-image-2.5-sunburst"])
def test_supported_models_resolve_to_openai_provider_with_that_model(model):
    provider = provider_for_model(model, "sk-fake")
    assert isinstance(provider, OpenAIImageProvider)
    assert provider._model == model


def test_dismissed_gpt_image_1_is_rejected():
    with pytest.raises(ImageGenerationError):
        provider_for_model("gpt-image-1", "sk-fake")


def test_missing_api_key_is_rejected():
    with pytest.raises(ImageGenerationError):
        provider_for_model("gpt-image-2.5-flare", "")


def test_generate_sends_the_chosen_model_to_openai():
    provider = provider_for_model("gpt-image-2.5-sunburst", "sk-fake")
    provider._client = MagicMock()
    provider._client.images.generate.return_value = SimpleNamespace(
        data=[SimpleNamespace(b64_json=base64.b64encode(b"png-bytes").decode())]
    )

    assert provider.generate("Un gatto astronauta") == b"png-bytes"
    assert provider._client.images.generate.call_args.kwargs["model"] == "gpt-image-2.5-sunburst"
