from services.mysql import Mysql
from dotenv import load_dotenv
import config as cfg
import requests
import json
import os

load_dotenv()

class LinkedIn:
    def __init__(self, client_id, client_secret, token):
        self.client_id = client_id
        self.client_secret = client_secret
        self.token = token
        self.base_url = os.getenv("LINKEDIN_BASE_URL")

    # Invio del post su LinkedIn
    def send(self, content, img_path):
        # URL dell'endpoint
        url = f"{self.base_url}/ugcPosts"

        headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
            "X-Restli-Protocol-Version": "2.0.0"
        }

        # author = f"urn:li:person:{self.get_person_urn()}"
        author = f"urn:li:organization:{self.get_company_urn()}"

        payload = {
            "author": author,
            "lifecycleState": "PUBLISHED",
            "specificContent": {
                "com.linkedin.ugc.ShareContent": {
                    "shareCommentary": {
                        "text": f"{content}"
                    },
                    "shareMediaCategory": "NONE"
                }
            },
            "visibility": {
                "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"
            }
        }

        # Effettua la richiesta POST
        response = requests.post(url, headers=headers, data=json.dumps(payload))

        # Mostra il risultato
        print(f'Status Code: {response.status_code}')
        print('Response:')
        print(response.json())

        return None, None

    def delete(self, post_id):
        return None

    # Recupero il person URN da MySQL o da LinkedIn
    # Il person URN è l'ID del profilo privato, quindi se si vuoi pubblicare
    # su LinkedIn con il proprio profilo, si deve utilizzare questo ID
    def get_person_urn(self):
        mysql = Mysql()
        mysql.connect()

        row = mysql.query(f"""
                        SELECT  {cfg.DB_PREFIX}settings.linkedin_person_urn AS linkedin_person_urn
                            FROM {cfg.DB_PREFIX}settings

                        WHERE {cfg.DB_PREFIX}settings.linkedin_client_id = "{self.client_id}"
                        """)

        if row[0]['linkedin_person_urn'] is None:
            # URL dell'endpoint
            url = f"{self.base_url}/me"

            # Headers
            headers = {
                'Authorization': f'Bearer {self.token}',
                'Connection': 'Keep-Alive'
            }

            # Effettua la richiesta
            response = requests.get(url, headers=headers)

            # Controlla il risultato
            if response.status_code == 200:
                data = response.json()
                person_urn = data.get('id')

                mysql.query(
                    query=f"UPDATE {cfg.DB_PREFIX}settings SET linkedin_person_urn = %s WHERE linkedin_client_id = %s",
                    parameters=(person_urn, self.client_id)
                )
            else:
                print(f'Errore: {response.status_code}')
                print(response.text)

                return None

        else:
            person_urn = row[0]['linkedin_person_urn']

        mysql.close()

        return person_urn

    # Recupero il company URN da MySQL
    # il company URN è l'ID della pagina gestita da un amministratore di LinkedIn
    def get_company_urn(self):
        mysql = Mysql()
        mysql.connect()

        row = mysql.query(f"""
                        SELECT  {cfg.DB_PREFIX}settings.linkedin_company_urn AS linkedin_company_urn
                            FROM {cfg.DB_PREFIX}settings

                        WHERE {cfg.DB_PREFIX}settings.linkedin_client_id = "{self.client_id}"
                        """)

        if row[0]['linkedin_company_urn'] is not None:
            company_urn = row[0]['linkedin_company_urn']
        else:
            return None

        mysql.close()

        return company_urn
