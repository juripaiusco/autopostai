import os
import json
import requests

class Meta:
    def __init__(
        self,
        page_id = os.getenv("META_PAGE_ID"),
        user_access_token = os.getenv("META_USER_ACCESS_TOKEN")
    ):

        self.META_API_BASE_URL = os.getenv("META_API_BASE_URL")
        self.META_PAGE_ID = page_id
        self.META_USER_ACCESS_TOKEN = user_access_token


    # Recupero l'accesso token della pagina
    def fb_page_access_token(self):

        url = f"{self.META_API_BASE_URL}/me/accounts"
        params = {"access_token": self.META_USER_ACCESS_TOKEN}

        response = requests.get(url, params=params)

        if response.status_code == 200:

            pagine = response.json().get("data", [])
            return next((pagina["access_token"] for pagina in pagine if pagina["id"] == self.META_PAGE_ID), None)

        else:
            print("Errore nel recupero delle pagine.")
            print("Codice di stato:", response.status_code)
            print("Errore:", response.json())
            return []

        fb_page_access_token = 0

        return fb_page_access_token


    # Viene creato un post sulla pagina Facebok
    def fb_generate_post(self, msg, img_url = None):

        response = []

        fb_page_access_token = self.fb_page_access_token();
        if img_url is None:
            response = requests.post(
                f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}/feed",
                data={
                    "message": msg,
                    "access_token": fb_page_access_token,
                }
            )

        if isinstance(img_url, list):
            img_url_array = img_url
            media_ids = []

            for img_url in img_url_array:
                img_upload_response = requests.post(
                    f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}/photos",
                    data={
                        "url": img_url,
                        "published": "false",
                        "access_token": fb_page_access_token
                    }
                )
                media_ids.append({"media_fbid": img_upload_response.json()["id"]})

            # Post finale con immagini collegate
            response = requests.post(
                f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}/feed",
                data={
                    "message": msg,
                    "attached_media": json.dumps(media_ids),
                    "access_token": fb_page_access_token,
                }
            )

        if response.status_code == 200:
            # print("Post Facebook pubblicato con successo!")
            # print("Risposta API:", response.json())
            return response.json().get("post_id") or response.json().get("id")
        else:
            print("Errore nella pubblicazione del post.")
            print("Codice di stato:", response.status_code)
            print("Errore:", response.json())


    def fb_get_comments(self, post_id):
        url = f"{self.META_API_BASE_URL}/{post_id}/comments"
        params = {"access_token": self.fb_page_access_token()}

        response = requests.get(url, params=params)

        return response.json()


    def fb_reply_comments(self, comment_id, reply_message):
        url = f"{self.META_API_BASE_URL}/{comment_id}/comments"
        payload = {
            "message": reply_message,
            "access_token": self.fb_page_access_token(),
        }

        response = requests.post(url, data=payload)

        return response.json().get("id")

    def fb_delete(self, post_id):
        url = f"{self.META_API_BASE_URL}/{post_id}"
        params = {"access_token": self.fb_page_access_token()}

        response = requests.delete(url, params=params)

        if response.status_code == 200:
            return post_id if response.json().get("success") == True else None
        else:
            print(f'Errore durante l\'eliminazione del post da Facebook: {response.text}')


    # Recupero l'ID dell'account Instagram collegato alla pagina Facebook
    def ig_get_instagram_account_id(self):

        url = f"{self.META_API_BASE_URL}/{self.META_PAGE_ID}"
        params = {
            "fields": "instagram_business_account",
            "access_token": self.META_USER_ACCESS_TOKEN,
        }

        response = requests.get(url, params=params)

        if response.status_code == 200:
            instagram_business_account = (response.json()
                                          .get("instagram_business_account", [])
                                          .get("id", []))
            return instagram_business_account

        else:
            print("Errore nel recupero delle pagine.")
            print("Codice di stato:", response.status_code)
            print("Errore:", response.json())
            return []


    # Instagram Creazione del post
    def ig_generate_post(self, msg, img_url=""):

        url = f"{self.META_API_BASE_URL}/{self.ig_get_instagram_account_id()}/media"
        payload = {
            "image_url": img_url,
            "caption": msg,
            "access_token": self.fb_page_access_token(),
        }

        response = requests.post(url, data=payload)
        if response.status_code == 200:
            # print("Post Instagram caricato con successo!")
            # print("Risposta API:", response.json())
            post_id = response.json().get("id")
            post_id_publish = self.ig_pubblicate_post(post_id)
            permalink = self.ig_getDataPost(post_id_publish, "permalink")

            return post_id_publish, permalink
        else:
            print("Errore nel caricamento dell'immagine:", response.json())

    # Pubblicazione del post
    def ig_pubblicate_post(self, id):

        url = f"{self.META_API_BASE_URL}/{self.ig_get_instagram_account_id()}/media_publish"
        payload = {
            "creation_id": id,
            "access_token": self.fb_page_access_token(),
        }

        response = requests.post(url, data=payload)
        if response.status_code == 200:
            # print("Post Instagram pubblicato con successo!")
            # print("Risposta API:", response.json())
            post_id = response.json().get("id")
            return post_id
        else:
            print("Errore nella pubblicazione:", response.json())

    def ig_getDataPost(self, post_id, fields):
        url = f"{self.META_API_BASE_URL}/{post_id}"
        params = {
            "fields": f"{fields}",
            "access_token": self.fb_page_access_token(),
        }

        response = requests.get(url, params=params)
        return response.json().get(fields)


    def ig_get_comments(self, post_id):
        url = f"{self.META_API_BASE_URL}/{post_id}/comments?fields=text,from,timestamp"
        params = {"access_token": self.fb_page_access_token()}

        response = requests.get(url, params=params)

        return response.json()


    def ig_reply_comments(self, comment_id, reply_message):
        url = f"{self.META_API_BASE_URL}/{comment_id}/replies"
        payload = {
            "message": reply_message,
            "access_token": self.fb_page_access_token(),
        }

        response = requests.post(url, data=payload)

        return response.json().get("id")

    def ig_delete(self, post_id):
        url = f"{self.META_API_BASE_URL}/{post_id}"
        params = {"access_token": self.fb_page_access_token()}

        response = requests.delete(url, params=params)

        if response.status_code == 200:
            return post_id if response.json().get("success") == True else None
        else:
            print(f'Errore durante l\'eliminazione del post da Instagram: {response.text}')
