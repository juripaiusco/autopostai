import os
import re
import json
import requests
from dotenv import load_dotenv
import config as cfg
from services.mysql import Mysql

load_dotenv()

class Mailchimp:
    def __init__(self, api_key, datacenter, list_id):
        self.api_key = api_key
        self.datacenter = datacenter
        self.list_id = list_id
        self.base_url = os.getenv("MAILCHIMP_BASE_URL").replace('[DATACENTER]', datacenter)

    def send(self, subject, content, img, data):
        # Preparo il contenuto ---------------------------------------------
        if img is not None:
            content = (f"<img src=\"{img}\""
                       f"style=\"width:100%; max-width:600px; height:auto; display:block; margin: 0 auto;\">"
                       f"<br>"
                       f"{content}")

        html_content = data['mailchimp_template'].replace('[content]', content)
        html_content = self.parse_cta_shortcode(html_content, data)
        # END - Preparo il contenuto ---------------------------------------

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

    def delete(self, post_id):
        headers = {
            "Authorization": f"apikey {self.api_key}",
            "Content-Type": "application/json"
        }

        url = f"{self.base_url}/campaigns/{post_id}"
        response = requests.delete(url, headers=headers)

        if response.status_code == 204:
            return post_id
        else:
            return None

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

            # Recupera valori dai parametri (con fallback)
            post_id = attrs.get("id", None)
            text = attrs.get("text", None)
            url = self.get_post_url(post_id, channel, data_type, data)

            html = data['mailchimp_template_cta'].replace('[text]', text).replace('[url]', url)

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
