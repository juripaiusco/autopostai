"""Client Brevo (creazione + invio campagna email).

Porting di `services/brevo.py` di v1. Brevo puo' inviare a piu' liste (listIds e'
un array). Come per Mailchimp, l'HTML arriva gia' assemblato dalla strategia
`publishing/newsletter.py`.
"""

from __future__ import annotations

import requests

from publisher import config


class Brevo:
    def __init__(self, api_key: str):
        self.api_key = api_key
        self.base_url = config.BREVO_BASE_URL

    def _headers(self) -> dict:
        return {"accept": "application/json", "api-key": self.api_key, "content-type": "application/json"}

    def send(self, subject: str, html_content: str, from_name: str, from_email: str, list_ids: list[int]) -> tuple[str, str]:
        campaign = requests.post(
            f"{self.base_url}/emailCampaigns",
            headers=self._headers(),
            json={
                "sender": {"name": from_name, "email": from_email},
                "name": subject,
                "subject": subject,
                "htmlContent": html_content,
                "replyTo": from_email,
                "recipients": {"listIds": list_ids},
                "inlineImageActivation": False,
                "mirrorActive": True,
            },
        )
        campaign.raise_for_status()
        post_id = campaign.json().get("id")
        post_url = campaign.json().get("url")

        requests.post(f"{self.base_url}/emailCampaigns/{post_id}/sendNow", headers=self._headers()).raise_for_status()

        return post_id, post_url

    def share_link(self, post_id: str) -> str | None:
        """URL pubblico d'archivio della campagna (usato dal task_complete)."""
        resp = requests.get(f"{self.base_url}/emailCampaigns/{post_id}", headers=self._headers())
        resp.raise_for_status()
        return resp.json().get("shareLink")
