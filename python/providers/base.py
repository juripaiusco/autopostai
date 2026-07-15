class ImageGenerationError(Exception):
    """Raised when a provider fails to produce an image."""


class ImageProvider:
    def generate(self, prompt: str) -> bytes:
        """Return PNG/JPEG bytes for the given prompt, or raise ImageGenerationError."""
        raise NotImplementedError
