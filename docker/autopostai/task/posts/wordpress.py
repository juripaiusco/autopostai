from task.posts.base import BasePost
import config as cfg
from datetime import datetime
from typing import List
import requests
from requests.auth import HTTPBasicAuth
import markdown
from services.wordpress import Wordpress

class WordPressPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="WordPress", data=data, debug=debug)

    def prompt_get(self):
        # Creo il prompt
        prompt = ("Generami un articolo per wordpress formattato in Markdown, "
                  "con un titolo (#), sottotitoli (##), elenchi e grassetto (**bold**)."
                  "Non usare ```markdown all'inizio e ``` alla fine.")

        if self.data['ai_prompt_post'] is not None:
            prompt = f"{prompt} {self.data['ai_prompt_post']}"

        return prompt

    def get_data(self, markdown_content):
        # Separare il titolo dal resto del contenuto
        lines = markdown_content.strip().split("\n", 1)
        title = lines[0].replace("#", "").strip()  # Prende solo il testo dopo `#`
        body = lines[1].strip() if len(lines) > 1 else ""  # Il resto del contenuto
        body_html = markdown.markdown(body)

        return title, body_html

    def wordpress_init(self):
        return Wordpress(
            wordpress_url=self.data['wordpress_url'],
            wordpress_username=self.data['wordpress_username'],
            wordpress_password=self.data['wordpress_password']
        )

    def send(self, content):
        title, body = self.get_data(content)
        cat_id = self.data.get('wordpress_cat_id')

        # Assicuriamoci che cat_id sia un numero valido
        if not isinstance(cat_id, int):
            print(f"⚠️ Errore: ID categoria non valido ({cat_id})")
            cat_id = None

        post_id, post_url = self.wordpress_init().send(
            title=title,
            content=body,
            img_path=self.img_path_get() if self.data['img'] else None,
            cat_id=cat_id,
        )

        if self.debug:
            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "WordPress - post ID:",
                  post_id)

        return post_id, post_url

        # if self.data['wordpress_url'] is not None:
        #     auth = HTTPBasicAuth(self.data['wordpress_username'], self.data['wordpress_password'])
        #
        #     title, body = self.get_data(content)
        #     cat_id = self.data.get('wordpress_cat_id')
        #
        #     # Assicuriamoci che cat_id sia un numero valido
        #     if not isinstance(cat_id, int):
        #         print(f"⚠️ Errore: ID categoria non valido ({cat_id})")
        #         cat_id = None
        #
        #     data = {
        #         "title": title,
        #         "content": body,
        #         "status": "publish",
        #         "categories": [cat_id] if cat_id else []
        #     }
        #
        #     response = requests.post(
        #         f"{self.data['wordpress_url']}/wp-json/wp/v2/posts",
        #         auth=auth,
        #         json=data
        #     )
        #
        #     post_id = response.json().get("id")
        #     post_url = response.json().get("link")
        #
        #     # Upload immagine
        #     if self.data['img']:
        #         # Percorso del file da caricare
        #         file_path = self.img_path_get()
        #
        #         # URL API per caricare l'immagine
        #         url = f"{self.data['wordpress_url']}/wp-json/wp/v2/media"
        #
        #         # Aprire il file e inviare la richiesta
        #         with open(file_path, "rb") as img:
        #             files = {"file": img}
        #             headers = {"Content-Disposition": f"attachment; filename={file_path}"}
        #             response = requests.post(url, auth=auth, files=files, headers=headers)
        #
        #         # Ottenere l'ID dell'immagine caricata
        #         image_id = response.json().get("id")
        #
        #         # URL per aggiornare il post
        #         url = f"{self.data['wordpress_url']}/wp-json/wp/v2/posts/{post_id}"
        #
        #         # Dati per assegnare l'immagine come immagine in evidenza
        #         data = {"featured_media": image_id}
        #
        #         # Invia la richiesta PATCH per aggiornare il post
        #         response = requests.post(url, auth=auth, json=data)
        #
        #     if self.debug:
        #         print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "WordPress - post ID:", response.json().get("id"))
        #
        #     return post_id, post_url

    def update(self, post_id, content):
        title, body = self.get_data(content)

        return self.wordpress_init().update(
            post_id=post_id,
            title=title,
            content=body,
        )

        # if self.data['wordpress_url'] is not None:
        #     auth = HTTPBasicAuth(self.data['wordpress_username'], self.data['wordpress_password'])
        #
        #     title, body = self.get_data(content)
        #
        #     data = {
        #         "title": title,
        #         "content": body,
        #         "status": "publish"
        #     }
        #
        #     response = requests.patch(
        #         f"{self.data['wordpress_url']}/wp-json/wp/v2/posts/{post_id}",
        #         auth=auth,
        #         json=data
        #     )
        #
        #     return response.json().get("id")

    def delete(self, post_id):
        return self.wordpress_init().delete(post_id)

        # if self.data['wordpress_url'] is not None:
        #     auth = HTTPBasicAuth(self.data['wordpress_username'], self.data['wordpress_password'])
        #
        #     response = requests.delete(
        #         f"{self.data['wordpress_url']}/wp-json/wp/v2/posts/{post_id}",
        #         auth=auth
        #     )
        #
        #     return response.json().get("id")
