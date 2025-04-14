import os
import re
import json
import requests
from dotenv import load_dotenv
import config as cfg
from services.mysql import Mysql

load_dotenv()

class Brevo:
    def __init__(self, api_key, list_id):
        self.api_key = api_key
        self.list_id = list_id
        self.base_url = os.getenv("BREVO_BASE_URL")

    def send(self, subject, content, img, data):
        # Preparo il contenuto ---------------------------------------------
        if img is not None:
            content = (f"<img src=\"{img}\""
                       f"style=\"width:100%; max-width:600px; height:auto; display:block; margin: 0 auto;\">"
                       f"<br>"
                       f"{content}")

        html_content = data['brevo_template'].replace('[content]', content)
        html_content = self.parse_cta_shortcode(html_content, data)
        # END - Preparo il contenuto ---------------------------------------

        headers = {
            "accept": "application/json",
            "api-key": self.api_key,
            "content-type": "application/json"
        }

        url = f"{self.base_url}/emailCampaigns"
        data_json = {
            "sender": {
                "name": data['brevo_from_name'],
                "email": data['brevo_from_email']
            },
            "name": subject,
            "subject": subject,
            "htmlContent": html_content,
            "recipients": {
                "listIds": [int(self.list_id)]
            },
            "inlineImageActivation": False,
            "mirrorActive": True,
            # "scheduledAt": None  # oppure "2025-04-08T10:00:00+01:00" per pianificare
        }

        response = requests.post(url, json=data_json, headers=headers)

        if response.status_code != 201:
            print("error", "Errore nella creazione della campagna", "details", response.json())

        post_id = response.json().get('id')
        post_url = response.json().get('url')

        response = requests.post(
            f"{self.base_url}/emailCampaigns/{post_id}/sendNow",
            headers=headers
        )

        if response.status_code != 204:
            print("error", "Errore nell'invio della campagna", "details", response.json())

        # return None, None
        return post_id, post_url

    def save_url(self, post_id, data):
        headers = {
            "accept": "application/json",
            "api-key": self.api_key,
            "content-type": "application/json"
        }

        url = f"{self.base_url}/emailCampaigns/{post_id}"

        response = requests.get(url, headers=headers)

        return response.json().get('shareLink')


    def parse_cta_shortcode(self, content, data):
        shortcode_pattern = r'\[cta(.*?)\]'  # Prende tutto quello dentro [cta ...]

        def replace_shortcode(match):
            raw_attrs = match.group(1)

            # Trova tutte le coppie chiave=valore o chiave="valore con spazi"
            attr_pattern = r'(\w+)=["\']?([^"\']+)["\']?'
            attrs = dict(re.findall(attr_pattern, raw_attrs))

            # Trova i flag (parametri senza "=")
            flag_pattern = r'\b(?!\w+=")[a-zA-Z]+\b'
            flags = re.findall(flag_pattern, raw_attrs)
            for flag in flags:
                attrs[flag] = True

            # Recupera valori dai parametri (con fallback)
            post_id = attrs.get("id", None)
            url = attrs.get("url", None)
            text = attrs.get("text", None)
            data_type = None
            channel = None

            # 🔍 Verifica se un parametro esiste
            if "url" in attrs:
                data_type = "url"

            if "WordPress" in attrs:
                channel = "WordPress"

            if "Facebook" in attrs:
                channel = "Facebook"

            if "Instagram" in attrs:
                channel = "Instagram"

            if url is True:
                url = self.get_post_url(post_id, channel, data_type, data)

            html = data['brevo_template_cta'].replace('[text]', text).replace('[url]', url)

            return html

        return re.sub(shortcode_pattern, replace_shortcode, content)

    def get_post_url(self, post_id, channel, data_type, data):
        mysql = Mysql()
        mysql.connect()

        row = mysql.query(f"""
                SELECT  {cfg.DB_PREFIX}posts.user_id AS user_id,
                        {cfg.DB_PREFIX}posts.channels AS channels
                    FROM {cfg.DB_PREFIX}posts
                WHERE {cfg.DB_PREFIX}posts.id = {post_id}
                    AND {cfg.DB_PREFIX}posts.user_id = {data.get('user_id')}
            """)

        if row[0]['channels'] is not None:
            channels = json.loads(row[0]['channels'])

            for i in channels:
                if (channels[i]['name'] == channel
                    and channels[i]['on'] == '1'
                    and channels[i]['id'] is not None):
                    return channels[i].get(data_type, "[URL non disponibile]")

        mysql.close()
