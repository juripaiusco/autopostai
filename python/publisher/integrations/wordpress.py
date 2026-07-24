"""Client WordPress REST API.

Porting di `services/wordpress.py` di v1: pubblica un articolo, carica le immagini
come media, e per i post multi-immagine costruisce il blocco gallery GLightbox.
Auth via HTTP Basic (application password).
"""

from __future__ import annotations

import requests
from requests.auth import HTTPBasicAuth

_GALLERY_OPEN = (
    '<!-- wp:gallery -->'
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">'
    '<figure class="wp-block-gallery has-nested-images columns-default is-cropped '
    'wp-block-gallery-1 is-layout-flex wp-block-gallery-is-layout-flex">'
)
_GALLERY_CLOSE = (
    '</figure>'
    '<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>'
    '<script>document.addEventListener("DOMContentLoaded",function(){GLightbox({selector:".glightbox"});});</script>'
    '<!-- /wp:gallery -->'
)


class WordPress:
    def __init__(self, url: str, username: str, password: str):
        self.url = url
        self.auth = HTTPBasicAuth(username, password)

    def _headers(self, extra: dict | None = None) -> dict:
        headers = {"User-Agent": "FaPer3 (AutoPostAI)"}
        if extra:
            headers.update(extra)
        else:
            headers["Content-Type"] = "application/json"
        return headers

    def publish(self, title: str, content: str, image_paths: list[str], category_ids: list) -> tuple[str, str, str | None]:
        gallery_html = None
        uploaded = [self._upload_image(p) for p in (image_paths or [])]

        if len(uploaded) > 1:
            gallery_html = _GALLERY_OPEN
            for media in uploaded[1:]:  # la prima diventa featured image
                gallery_html += (
                    '<figure class="wp-block-image size-large">'
                    f'<a href="{media["url"]}" class="glightbox" data-gallery="mygallery">'
                    f'<img decoding="async" alt="" class="wp-image-{media["id"]}" '
                    f'data-id="{media["id"]}" data-src="{media["url"]}" src="{media["url"]}"></a></figure>'
                )
            gallery_html += _GALLERY_CLOSE
            content = gallery_html + "<br>" + content

        data = {
            "title": title,
            "content": content,
            "status": "publish",
            "categories": category_ids or [],
        }
        if uploaded:
            data["featured_media"] = uploaded[0]["id"]

        resp = requests.post(f"{self.url}/wp-json/wp/v2/posts", headers=self._headers(), auth=self.auth, json=data)
        resp.raise_for_status()
        body = resp.json()
        return body.get("id"), body.get("link"), gallery_html

    def _upload_image(self, image_path: str) -> dict:
        with open(image_path, "rb") as img:
            resp = requests.post(
                f"{self.url}/wp-json/wp/v2/media",
                headers=self._headers({"Content-Disposition": f"attachment; filename={image_path}"}),
                auth=self.auth,
                files={"file": img},
            )
        resp.raise_for_status()
        body = resp.json()
        return {"id": body.get("id"), "url": body.get("source_url")}

    def delete(self, post_id: str) -> str | None:
        resp = requests.delete(f"{self.url}/wp-json/wp/v2/posts/{post_id}", headers=self._headers(), auth=self.auth)
        resp.raise_for_status()
        return resp.json().get("id")
