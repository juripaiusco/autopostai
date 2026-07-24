"""Client Mailchimp (creazione + invio campagna).

Porting di `services/mailchimp.py` di v1, ma piu' snello: l'assemblaggio del
contenuto (template + immagine + shortcode CTA) lo fa la strategia
`publishing/newsletter.py`; qui riceviamo l'HTML gia' pronto e ci occupiamo solo
del ciclo campagna: crea -> imposta contenuto -> invia.
"""

from __future__ import annotations

import requests

from publisher import config


class Mailchimp:
    def __init__(self, api_key: str, datacenter: str):
        self.api_key = api_key
        self.base_url = config.MAILCHIMP_BASE_URL.replace("[DATACENTER]", datacenter or "")

    def _headers(self) -> dict:
        return {"Authorization": f"apikey {self.api_key}", "Content-Type": "application/json"}

    def send(self, subject: str, html_content: str, from_name: str, from_email: str, list_id: str) -> tuple[str, str]:
        campaign = requests.post(
            f"{self.base_url}/campaigns",
            headers=self._headers(),
            json={
                "type": "regular",
                "recipients": {"list_id": list_id},
                "settings": {"subject_line": subject, "from_name": from_name, "reply_to": from_email},
            },
        )
        campaign.raise_for_status()
        post_id = campaign.json().get("id")
        post_url = campaign.json().get("archive_url")

        requests.put(
            f"{self.base_url}/campaigns/{post_id}/content",
            headers=self._headers(),
            json={"html": html_content},
        ).raise_for_status()

        requests.post(f"{self.base_url}/campaigns/{post_id}/actions/send", headers=self._headers()).raise_for_status()

        return post_id, post_url

    def delete(self, post_id: str) -> str | None:
        resp = requests.delete(f"{self.base_url}/campaigns/{post_id}", headers=self._headers())
        return post_id if resp.status_code == 204 else None
