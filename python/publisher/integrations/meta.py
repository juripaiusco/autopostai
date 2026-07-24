"""Client Meta Graph API (Facebook + Instagram).

Porting di `services/meta.py` di v1. Differenza v2: il token utente e' quello
per-account (`settings.meta_token`, modello BYOK) invece di una variabile
d'ambiente globale `META_USER_ACCESS_TOKEN`. Da quel token si ricava il page
access token e, per Instagram, l'id del business account collegato alla pagina.
"""

from __future__ import annotations

import json

import requests

from publisher import config


class Meta:
    def __init__(self, page_id: str, user_access_token: str):
        self.base_url = config.META_API_BASE_URL
        self.page_id = page_id
        self.user_access_token = user_access_token

    # --- token / account resolution --------------------------------------
    def page_access_token(self) -> str | None:
        resp = requests.get(
            f"{self.base_url}/me/accounts",
            params={"access_token": self.user_access_token},
        )
        resp.raise_for_status()
        pages = resp.json().get("data", [])
        return next((p["access_token"] for p in pages if p["id"] == self.page_id), None)

    def instagram_account_id(self) -> str | None:
        resp = requests.get(
            f"{self.base_url}/{self.page_id}",
            params={
                "fields": "instagram_business_account",
                "access_token": self.user_access_token,
            },
        )
        resp.raise_for_status()
        return (resp.json().get("instagram_business_account") or {}).get("id")

    # --- Facebook ---------------------------------------------------------
    def fb_publish(self, message: str, image_urls: list[str] | None = None) -> tuple[str, str]:
        token = self.page_access_token()

        if not image_urls:
            resp = requests.post(
                f"{self.base_url}/{self.page_id}/feed",
                data={"message": message, "access_token": token},
            )
        else:
            media_ids = []
            for url in image_urls:
                up = requests.post(
                    f"{self.base_url}/{self.page_id}/photos",
                    data={"url": url, "published": "false", "access_token": token},
                )
                up.raise_for_status()
                media_ids.append({"media_fbid": up.json()["id"]})
            resp = requests.post(
                f"{self.base_url}/{self.page_id}/feed",
                data={
                    "message": message,
                    "attached_media": json.dumps(media_ids),
                    "access_token": token,
                },
            )

        resp.raise_for_status()
        post_id = resp.json().get("post_id") or resp.json().get("id")
        return post_id, f"https://www.facebook.com/{self.page_id}/posts/{post_id}"

    def fb_delete(self, post_id: str) -> str | None:
        resp = requests.delete(f"{self.base_url}/{post_id}", params={"access_token": self.page_access_token()})
        resp.raise_for_status()
        return post_id if resp.json().get("success") else None

    # --- Instagram --------------------------------------------------------
    def ig_publish(self, caption: str, image_urls: list[str]) -> tuple[str, str]:
        token = self.page_access_token()
        ig_id = self.instagram_account_id()
        media_url = f"{self.base_url}/{ig_id}/media"
        images = image_urls[: config.INSTAGRAM_MAX_IMAGES]

        if len(images) == 1:
            resp = requests.post(
                media_url,
                data={"image_url": images[0], "caption": caption, "access_token": token},
            )
            resp.raise_for_status()
            creation_id = resp.json()["id"]
        else:
            children = []
            for url in images:
                item = requests.post(
                    media_url,
                    data={"image_url": url, "is_carousel_item": "true", "access_token": token},
                )
                item.raise_for_status()
                children.append(item.json()["id"])
            resp = requests.post(
                media_url,
                data={
                    "media_type": "CAROUSEL",
                    "children": json.dumps(children),
                    "caption": caption,
                    "access_token": token,
                },
            )
            resp.raise_for_status()
            creation_id = resp.json()["id"]

        publish = requests.post(
            f"{self.base_url}/{ig_id}/media_publish",
            data={"creation_id": creation_id, "access_token": token},
        )
        publish.raise_for_status()
        post_id = publish.json().get("id")

        permalink = requests.get(
            f"{self.base_url}/{post_id}",
            params={"fields": "permalink", "access_token": token},
        ).json().get("permalink")

        return post_id, permalink

    def ig_delete(self, post_id: str) -> str | None:
        # L'API Instagram non permette di eliminare i post: v1 si limitava a
        # ricopiare l'id. Manteniamo lo stesso comportamento.
        return post_id

    # --- Commenti (facebook + instagram condividono l'endpoint Graph) -----
    def fb_get_comments(self, post_id: str) -> dict:
        resp = requests.get(
            f"{self.base_url}/{post_id}/comments",
            params={"access_token": self.page_access_token()},
        )
        return resp.json()

    def fb_reply_comment(self, comment_id: str, message: str) -> str | None:
        resp = requests.post(
            f"{self.base_url}/{comment_id}/comments",
            data={"message": message, "access_token": self.page_access_token()},
        )
        return resp.json().get("id")

    def ig_get_comments(self, post_id: str) -> dict:
        resp = requests.get(
            f"{self.base_url}/{post_id}/comments",
            params={"fields": "text,from,timestamp", "access_token": self.page_access_token()},
        )
        return resp.json()

    def ig_reply_comment(self, comment_id: str, message: str) -> str | None:
        resp = requests.post(
            f"{self.base_url}/{comment_id}/replies",
            data={"message": message, "access_token": self.page_access_token()},
        )
        return resp.json().get("id")
