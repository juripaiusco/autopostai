from services.mysql import Mysql
from dotenv import load_dotenv
import config as cfg
import requests
import json
import os
from urllib.parse import quote

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

        # author_urn = f"urn:li:person:{self.get_person_id()}"
        author_urn = f"urn:li:organization:{self.get_company_id()}"

        if img_path is None:
            payload = {
                "author": author_urn,
                "lifecycleState": "PUBLISHED",
                "specificContent": {
                    "com.linkedin.ugc.ShareContent": {
                        "shareCommentary": {
                            "text": f"{content}"
                        },
                        "shareMediaCategory": "NONE",
                    }
                },
                "visibility": {
                    "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"
                }
            }
        else:
            payload = {
                "author": author_urn,
                "lifecycleState": "PUBLISHED",
                "specificContent": {
                    "com.linkedin.ugc.ShareContent": {
                        "shareCommentary": {
                            "text": f"{content}"
                        },
                        "shareMediaCategory": "IMAGE",
                        "media": [
                            {
                                "status": "READY",
                                "media": self.upload_img(author=author_urn, img_path=img_path)
                            }
                        ]
                    }
                },
                "visibility": {
                    "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"
                }
            }

        # Effettua la richiesta POST
        response = requests.post(url, headers=headers, data=json.dumps(payload))

        if response.status_code != 201:
            return None, None

        # Mostra il risultato
        # print(f'Status Code: {response.status_code}')
        # print('Response:')
        # print(response.json())

        return response.json().get('id'), f"https://www.linkedin.com/feed/update/{response.json().get('id')}"

    def delete(self, post_id):
        # Endpoint DELETE
        # url = f"{self.base_url}/ugcPosts/{post_id}"
        raw_post_id = post_id
        post_id = raw_post_id.split(":")[-1]
        url = f"{self.base_url}/shares/{post_id}"

        # Headers
        headers = {
            'Authorization': f'Bearer {self.token}',
            'X-Restli-Protocol-Version': '2.0.0'
        }

        # Effettua la richiesta DELETE
        response = requests.delete(url, headers=headers)

        # Verifica risultato
        if response.status_code in [200, 204]:
            # Restituisco l'ID raw perché viene fatto un controllo se il post è stato
            # eliminato, e questo controllo viene fatto sugli ID del post, quindi l'ID
            # inizialmente salvato è raw, non il codice numerico.
            return raw_post_id
        else:
            print(f"❌ Errore nell'eliminazione: {response.status_code}")
            print(response.text)

    # Recupero il person URN da MySQL o da LinkedIn
    # Il person URN è l'ID del profilo privato, quindi se si vuoi pubblicare
    # su LinkedIn con il proprio profilo, si deve utilizzare questo ID
    def get_person_id(self):
        mysql = Mysql()
        mysql.connect()

        row = mysql.query(f"""
                        SELECT  {cfg.DB_PREFIX}settings.linkedin_person_id AS linkedin_person_id
                            FROM {cfg.DB_PREFIX}settings

                        WHERE {cfg.DB_PREFIX}settings.linkedin_client_id = "{self.client_id}"
                        """)

        if row[0]['linkedin_person_id'] is None:
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
                person_id = data.get('id')

                mysql.query(
                    query=f"UPDATE {cfg.DB_PREFIX}settings SET linkedin_person_id = %s WHERE linkedin_client_id = %s",
                    parameters=(person_id, self.client_id)
                )
            else:
                print(f'Errore: {response.status_code}')
                print(response.text)

                return None

        else:
            person_id = row[0]['linkedin_person_id']

        mysql.close()

        return person_id

    # Recupero il company URN da MySQL
    # il company URN è l'ID della pagina gestita da un amministratore di LinkedIn
    def get_company_id(self):
        mysql = Mysql()
        mysql.connect()

        row = mysql.query(f"""
                        SELECT  {cfg.DB_PREFIX}settings.linkedin_company_id AS linkedin_company_id
                            FROM {cfg.DB_PREFIX}settings

                        WHERE {cfg.DB_PREFIX}settings.linkedin_client_id = "{self.client_id}"
                        """)

        if row[0]['linkedin_company_id'] is not None:
            company_urn = row[0]['linkedin_company_id']
        else:
            return None

        mysql.close()

        return company_urn

    # Caricamento immagine su LinkedIn
    # L'immagine deve essere caricata prima di inviare il post su LinkedIn
    # Per caricare l'immagine, si utilizza l'endpoint /assets?action=registerUpload
    # Per inviare il post su LinkedIn, si utilizza l'endpoint /ugcPosts
    # Per caricare l'immagine e inviare il post su LinkedIn, si utilizza l'endpoint /ugcPosts/bulk
    def upload_img(self, author, img_path):
        # Registrazione dell'upload
        url = f"{self.base_url}/assets?action=registerUpload"

        headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
            "X-Restli-Protocol-Version": "2.0.0"
        }

        register_payload = {
            "registerUploadRequest": {
                "owner": author,
                "recipes": ["urn:li:digitalmediaRecipe:feedshare-image"],
                "serviceRelationships": [
                    {
                        "relationshipType": "OWNER",
                        "identifier": "urn:li:userGeneratedContent"
                    }
                ],
                "supportedUploadMechanism": ["SYNCHRONOUS_UPLOAD"]
            }
        }

        res = requests.post(url, headers=headers, data=json.dumps(register_payload))
        upload_data = res.json()

        upload_url = upload_data["value"]["uploadMechanism"]["com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"]["uploadUrl"]
        asset = upload_data["value"]["asset"]

        # Carica l'immagine
        with open(img_path, "rb") as f:
            image_data = f.read()

        upload_headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/octet-stream"
        }

        upload_res = requests.put(upload_url, headers=upload_headers, data=image_data)

        return asset

    def get_comments(self, post_id):
        # Estrai la parte finale per costruire l'URL
        encoded_urn = post_id.replace(":", "%3A")  # URL encode i due punti

        # URL dell'endpoint
        url = f"{self.base_url}/socialActions/{encoded_urn}/comments"

        headers = {
            "Authorization": f"Bearer {self.token}",
            "X-Restli-Protocol-Version": "2.0.0"
        }

        # Effettua la richiesta POST
        response = requests.get(url, headers=headers)

        return response.json()

    # Recupero i dati della persona tramite URN
    def get_author(self, actor_urn):
        # URL dell'endpoint
        person_id = actor_urn.split(":")[-1]
        url = f"{self.base_url}/people/(id:{person_id})"

        headers = {
            "Authorization": f"Bearer {self.token}",
            "X-Restli-Protocol-Version": "2.0.0"
        }

        # Effettua la richiesta POST
        response = requests.get(url, headers=headers)

        if response.status_code == 200:
            data = response.json()
            first_name = data.get("firstName", {}).get("localized", {}).get("it_IT", "")
            last_name = data.get("lastName", {}).get("localized", {}).get("it_IT", "")
            return first_name, last_name
        else:
            print(f"❌ Errore {response.status_code}: {response.text}")
            return None, None

    def reply_comments(self, comment_urn, actor_urn, actor_name, reply_message):
        encoded_comment_urn = quote(comment_urn, safe='')
        base_url = self.base_url.replace("/v2", "")
        url = f"{base_url}/rest/socialActions/{encoded_comment_urn}/comments"
        # url = f"{self.base_url}/socialActions/{encoded_comment_urn}/comments"

        headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
            "X-Restli-Protocol-Version": "2.0.0",
            "LinkedIn-Version": "202306"
            # "LinkedIn-Version": "202504"
            # "LinkedIn-Version": "202405"
        }

        author_urn = f"urn:li:organization:{self.get_company_id()}"

        if not f"{actor_name}" in reply_message:
            reply_message = reply_message[0].lower() + reply_message[1:]
            reply_message = f"{actor_name} {reply_message}"

        payload = {
            "actor": author_urn,
            "message": {
                "text": reply_message,
                    "attributes": [
                        {
                            "start": 0,
                            "length": len(actor_name),
                            "value": {
                                "com.linkedin.common.MemberAttributedEntity": {
                                    "member": actor_urn
                                }
                            }
                        }
                    ]
            },
            "parentComment": comment_urn
        }

        response = requests.post(url, headers=headers, json=payload)

        if response.status_code in [200, 201, 204]:
            return response.json().get("id")
        else:
            print(f"❌ Errore commento: {response.status_code}")

            print("Messaggio:", reply_message)
            print("Mention URN:", actor_urn)
            print("Length mention:", len(actor_name) + 1)
            print("Payload:", json.dumps(payload, indent=2))

            print(response.json())
