import os
import json
import requests
from dotenv import load_dotenv

load_dotenv()

class Mailchimp:
    def __init__(self, api_key, datacenter, list_id):
        self.api_key = api_key
        self.datacenter = datacenter
        self.list_id = list_id
        self.base_url = os.getenv("MAILCHIMP_BASE_URL").replace('[DATACENTER]', datacenter)

    def send(self, subject, content, img, data):
        headers = {
            "Authorization": f"apikey {self.api_key}",
            "Content-Type": "application/json"
        }

        # Imposto la campagna ---------------------------------------------
        url = f"{self.base_url}/campaigns"
        data_json = {
            "type": "regular",
            "recipients": {"list_id": self.list_id},
            "settings": {
                "subject_line": subject,
                "from_name": data['mailchimp_from_name'],
                "reply_to": data['mailchimp_from_email']
            }
        }
        response = requests.post(url, headers=headers, data=json.dumps(data_json))

        if response.status_code != 200:
            print("error", "Errore nella creazione della campagna", "details", response.json())

        post_id = response.json().get('id')
        post_url = response.json().get('archive_url')
        # END - Imposto la campagna ---------------------------------------------

        # Inserisco il contenuto nella mail -------------------------------------
        if img is not None:
            content = (f"<img src=\"{img}\""
                       f"style=\"width:100%; max-width:600px; height:auto; display:block; margin: 0 auto;\">"
                       f"<br>"
                       f"{content}")

        html_content = data['mailchimp_template'].replace('[content]', content)

        url = f"{self.base_url}/campaigns/{post_id}/content"
        data_json = {
            "html": html_content,
        }
        response = requests.put(url, headers=headers, data=json.dumps(data_json))

        if response.status_code != 200:
            print("error", "Errore nella creazione del contenuto", "details", response.json())
        # END _ Inserisco il contenuto nella mail --------------------------------

        # Invio la campagna ------------------------------------------------------
        url = f"{self.base_url}/campaigns/{post_id}/actions/send"
        response = requests.post(url, headers=headers)

        if response.status_code != 204:
            print("error", "Errore nell'invio della campagna", "details", response.json())
        # END - Invio la campagna ------------------------------------------------

        return post_id, post_url
