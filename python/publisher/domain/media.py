"""Gestione immagini del post (path locale, url pubblico, crop quadrato).

Porting di `task/posts/base.py` di v1, ma con un modello immagini UNICO: in v2
`posts.img` e' sempre un array JSON di filename (niente piu' il doppio trattamento
"stringa singola vs array" che v1 aveva tra publish e reply).
"""

from __future__ import annotations

import json

from PIL import Image

from publisher import config


def _filenames(post: dict) -> list[str]:
    raw = post.get("img")
    if not raw:
        return []
    return json.loads(raw) if isinstance(raw, str) else list(raw)


def local_paths(post: dict) -> list[str]:
    """Percorsi su disco delle immagini del post."""
    return [f"{config.STORAGE_PATH}/posts/{post['id']}/{name}" for name in _filenames(post)]


def public_urls(post: dict, make_square: bool = False) -> list[str]:
    """URL pubblici delle immagini. Con make_square genera e linka le versioni quadrate."""
    names = _filenames(post)
    if not names:
        return []

    if make_square:
        square_names = []
        for name in names:
            square_name = f"square-{name}"
            _make_square(
                f"{config.STORAGE_PATH}/posts/{post['id']}/{name}",
                f"{config.STORAGE_PATH}/posts/{post['id']}/{square_name}",
            )
            square_names.append(square_name)
        names = square_names

    return [f"{config.APP_URL}/storage/posts/{post['id']}/{name}" for name in names]


def has_images(post: dict) -> bool:
    return bool(_filenames(post))


def _make_square(image_path: str, output_path: str) -> None:
    """Riquadra l'immagine su canvas quadrato nero (richiesto da Instagram)."""
    image = Image.open(image_path)
    width, height = image.size
    if width == height:
        image.save(output_path)
        return

    side = max(width, height)
    canvas = Image.new("RGB", (side, side), (0, 0, 0))
    canvas.paste(image, ((side - width) // 2, (side - height) // 2))
    canvas.save(output_path)
