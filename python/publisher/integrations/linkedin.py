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
import logging
import re
from urllib.parse import quote

from publisher import config
from publisher.integrations import http

log = logging.getLogger(__name__)


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

        resp = http.post(f"{self.base_url}/ugcPosts", headers=self._headers(), data=json.dumps(payload))
        if resp.status_code != 201:
            resp.raise_for_status()
        post_id = resp.json().get("id")
        return post_id, f"https://www.linkedin.com/feed/update/{post_id}"

    def _upload_image(self, author_urn: str, image_path: str) -> str | None:
        register = http.post(
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
            http.put(
                upload_url,
                headers={"Authorization": f"Bearer {self.token}", "Content-Type": "application/octet-stream"},
                data=f.read(),
            ).raise_for_status()

        return asset

    def delete(self, post_id: str) -> str | None:
        numeric_id = post_id.split(":")[-1]
        resp = http.delete(
            f"{self.base_url}/shares/{numeric_id}",
            headers={"Authorization": f"Bearer {self.token}", "X-Restli-Protocol-Version": "2.0.0"},
        )
        if resp.status_code == 404:
            log.warning("linkedin delete: share %s gia' rimosso (HTTP 404)", post_id)
            return post_id
        # Altri errori sollevano (loggati da posts_delete con il dettaglio), invece
        # di un None silenzioso che finiva nel log come "rimosso (id=None)".
        resp.raise_for_status()
        return post_id

    # --- Commenti ------------------------------------------------------
    def get_comments(self, post_id: str) -> dict:
        encoded_urn = post_id.replace(":", "%3A")
        resp = http.get(
            f"{self.base_url}/socialActions/{encoded_urn}/comments",
            headers={"Authorization": f"Bearer {self.token}", "X-Restli-Protocol-Version": "2.0.0"},
        )
        return resp.json()

    def get_author(self, actor_urn: str) -> tuple[str | None, str | None]:
        person_id = actor_urn.split(":")[-1]
        resp = http.get(
            f"{self.base_url}/people/(id:{person_id})",
            headers={"Authorization": f"Bearer {self.token}", "X-Restli-Protocol-Version": "2.0.0"},
        )
        if resp.status_code != 200:
            return None, None
        data = resp.json()
        first = data.get("firstName", {}).get("localized", {}).get("it_IT", "")
        last = data.get("lastName", {}).get("localized", {}).get("it_IT", "")
        return first, last

    def reply_comment(self, comment_urn: str, actor_urn: str, actor_name: str, reply_message: str) -> tuple[str | None, str]:
        """Risponde a un commento taggando l'autore (@mention posizionale).

        LinkedIn richiede la posizione esatta (start/length) del nome nel testo per
        renderlo come mention cliccabile. Se il nome non compare nel messaggio
        generato dal LLM, lo si antepone (comportamento di v1).
        """
        encoded_comment_urn = quote(comment_urn, safe="")
        base_url = self.base_url.replace("/v2", "")
        url = f"{base_url}/rest/socialActions/{encoded_comment_urn}/comments"

        match = re.search(rf"\b{re.escape(actor_name)}\b", reply_message, re.IGNORECASE)
        if match:
            start, length = match.start(), len(actor_name)
        else:
            first_name = actor_name.split(" ")[0]
            match = re.search(rf"\b{re.escape(first_name)}\b", reply_message, re.IGNORECASE)
            if match:
                start, length = match.start(), len(first_name)
            else:
                reply_message = f"{actor_name} {reply_message[0].lower()}{reply_message[1:]}"
                start, length = 0, len(actor_name)

        payload = {
            "actor": f"urn:li:organization:{self.company_id}",
            "message": {
                "text": reply_message,
                "attributes": [
                    {
                        "start": start,
                        "length": length,
                        "value": {"com.linkedin.common.MemberAttributedEntity": {"member": actor_urn}},
                    }
                ],
            },
            "parentComment": comment_urn,
        }

        resp = http.post(
            url,
            headers={
                "Authorization": f"Bearer {self.token}",
                "Content-Type": "application/json",
                "X-Restli-Protocol-Version": "2.0.0",
                "LinkedIn-Version": "202306",
            },
            json=payload,
        )
        if resp.status_code not in (200, 201, 204):
            resp.raise_for_status()
        return resp.json().get("id"), reply_message
