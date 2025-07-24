import requests
from requests.auth import HTTPBasicAuth

class Wordpress:
    def __init__(self, wordpress_url, wordpress_username, wordpress_password):
        self.wordpress_url = wordpress_url
        self.wordpress_username = wordpress_username
        self.wordpress_password = wordpress_password

    def auth(self):
        if self.wordpress_url is not None:
            return HTTPBasicAuth(self.wordpress_username, self.wordpress_password)

    def headers(self, headers_img=None):
        headers = {}

        if headers_img is None:
            headers['Content-Type'] = "application/json"

        if headers_img is not None:
            headers.update(headers_img)

        headers['User-Agent'] = "FaPer3 (AutoPostAI)"

        return headers

    def send(self, title, content, img_path, cat_id):
        data = {
            "title": title,
            "content": content,
            "status": "publish",
            "categories": cat_id if cat_id else []
        }

        response = requests.post(
            f"{self.wordpress_url}/wp-json/wp/v2/posts",
            headers=self.headers(),
            auth=self.auth(),
            json=data
        )

        post_id = response.json().get("id")
        post_url = response.json().get("link")

        # Upload immagine
        if img_path:
            # Aprire il file e inviare la richiesta
            with open(img_path, "rb") as img:
                files = {"file": img}
                response = requests.post(
                    f"{self.wordpress_url}/wp-json/wp/v2/media",
                    headers={
                        "Content-Disposition": f"attachment; filename={img_path}",
                        "User-Agent": "Mozilla/5.0 (AutoPostAI)"
                    },
                    auth=self.auth(),
                    files=files
                )

            # Ottenere l'ID dell'immagine caricata
            image_id = response.json().get("id")

            # Dati per assegnare l'immagine come immagine in evidenza
            data = {"featured_media": image_id}

            # Invia la richiesta PATCH per aggiornare il post
            response = requests.post(
                f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
                headers=self.headers(),
                auth=self.auth(),
                json=data
            )

        return post_id, post_url

    def update(self, post_id, title, content):
        data = {
            "title": title,
            "content": content,
            "status": "publish"
        }

        response = requests.post(
            f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
            headers=self.headers(),
            auth=self.auth(),
            json=data
        )

        return response.json().get("id")

    def delete(self, post_id):
        response = requests.delete(
            f"{self.wordpress_url}/wp-json/wp/v2/posts/{post_id}",
            headers=self.headers(),
            auth=self.auth()
        )

        return response.json().get("id")
