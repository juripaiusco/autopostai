"""Client LinkedIn UGC Posts.

Porting di `services/linkedin.py` di v1 (parte pubblicazione). Pubblica come
organizzazione (company URN). Le immagini vanno registrate via
/assets?action=registerUpload e caricate prima di allegarle al post.

Nota: v1 leggeva person_id/company_id dal DB dentro il client. Qui vengono passati
dal chiamante (il worker li ha gia' nella riga settings), cosi' il client resta
senza dipendenze dal DB.
"""

from __future__ import annotations

import json

import requests

from publisher import config


class LinkedIn:
    def __init__(self, token: str, company_id: str):
        self.base_url = config.LINKEDIN_BASE_URL
        self.token = token
        self.company_id = company_id

    def _headers(self) -> dict:
        return {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
            "X-Restli-Protocol-Version": "2.0.0",
        }

    def publish(self, content: str, image_paths: list[str]) -> tuple[str | None, str | None]:
        author_urn = f"urn:li:organization:{self.company_id}"

        media_category = "NONE"
        media_list = []
        if image_paths:
            media_category = "IMAGE"
            for i, path in enumerate(image_paths[: config.LINKEDIN_MAX_IMAGES]):
                asset = self._upload_image(author_urn, path)
                if asset:
                    media_list.append({"status": "READY", "media": asset, "title": {"text": f"Immagine {i + 1}"}})

        payload = {
            "author": author_urn,
            "lifecycleState": "PUBLISHED",
            "specificContent": {
                "com.linkedin.ugc.ShareContent": {
                    "shareCommentary": {"text": content},
                    "shareMediaCategory": media_category,
                    "media": media_list,
                }
            },
            "visibility": {"com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"},
        }

        resp = requests.post(f"{self.base_url}/ugcPosts", headers=self._headers(), data=json.dumps(payload))
        if resp.status_code != 201:
            resp.raise_for_status()
        post_id = resp.json().get("id")
        return post_id, f"https://www.linkedin.com/feed/update/{post_id}"

    def _upload_image(self, author_urn: str, image_path: str) -> str | None:
        register = requests.post(
            f"{self.base_url}/assets?action=registerUpload",
            headers=self._headers(),
            data=json.dumps(
                {
                    "registerUploadRequest": {
                        "owner": author_urn,
                        "recipes": ["urn:li:digitalmediaRecipe:feedshare-image"],
                        "serviceRelationships": [
                            {"relationshipType": "OWNER", "identifier": "urn:li:userGeneratedContent"}
                        ],
                        "supportedUploadMechanism": ["SYNCHRONOUS_UPLOAD"],
                    }
                }
            ),
        )
        register.raise_for_status()
        data = register.json()
        upload_url = data["value"]["uploadMechanism"][
            "com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"
        ]["uploadUrl"]
        asset = data["value"]["asset"]

        with open(image_path, "rb") as f:
            requests.put(
                upload_url,
                headers={"Authorization": f"Bearer {self.token}", "Content-Type": "application/octet-stream"},
                data=f.read(),
            ).raise_for_status()

        return asset

    def delete(self, post_id: str) -> str | None:
        numeric_id = post_id.split(":")[-1]
        resp = requests.delete(
            f"{self.base_url}/shares/{numeric_id}",
            headers={"Authorization": f"Bearer {self.token}", "X-Restli-Protocol-Version": "2.0.0"},
        )
        return post_id if resp.status_code in (200, 204) else None
