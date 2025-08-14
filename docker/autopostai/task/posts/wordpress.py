import json
from task.posts.base import BasePost
import config as cfg
from datetime import datetime
from typing import List
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

    def data_get(self, markdown_content):
        # Separare il titolo dal resto del contenuto
        lines = markdown_content.strip().split("\n", 1)
        title = lines[0].replace("#", "").strip()  # Prende solo il testo dopo `#`
        body = lines[1].strip() if len(lines) > 1 else ""  # Il resto del contenuto
        body_html = markdown.markdown(body)

        return title, body_html

    # Inizializza la connessione WordPress
    def wordpress_init(self):
        return Wordpress(
            wordpress_url=self.data['wordpress_url'],
            wordpress_username=self.data['wordpress_username'],
            wordpress_password=self.data['wordpress_password']
        )

    def categories_get(self, categories):
        return [cat['id'] for cat in categories if str(cat.get('on')) == '1']

    # Invio Post su WordPress
    def send(self, content):
        title, body = self.data_get(content)
        cat_id = self.data.get('wordpress_cat_id')

        # Assicuriamoci che cat_id sia un numero valido
        if not isinstance(cat_id, int):
            cat_id = None
        else:
            cat_id = [cat_id]

        channels = json.loads(self.data.get('channels'))
        selected_categories = self.categories_get(
            channels.get('wordpress', {}).get('options', {}).get('categories', [])
        )

        if selected_categories:
            cat_id = self.categories_get(channels.get('wordpress').get('options').get('categories'))

        post_id, post_url = self.wordpress_init().send(
            title=title,
            content=body,
            img_path=self.img_path_get(get_all_img=True) if self.data['img'] else None,
            cat_id=cat_id,
        )

        if self.debug:
            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "WordPress - post ID:",
                  post_id)

        return post_id, post_url

    # Aggiorno il post su WordPress
    def update(self, post_id, content):
        title, body = self.data_get(content)

        return self.wordpress_init().update(
            post_id=post_id,
            title=title,
            content=body,
        )

    # Elimino il post da WordPress
    def delete(self, post_id):
        return self.wordpress_init().delete(post_id)
