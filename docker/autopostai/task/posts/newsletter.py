from task.posts.base import BasePost
import config as cfg
from typing import List
from services.mailchimp import Mailchimp
from datetime import datetime
import markdown

class NewsletterPost(BasePost):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name=self.get_channel_name(data), data=data, debug=debug)

    def prompt_get(self):
        # Creo il prompt
        prompt = ("Generami una mail per una newsletter formattata in Markdown, "
                  "con un titolo (#), sottotitoli (##), ed eventualmente elenchi e grassetto (**bold**)."
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

    def send(self, content):
        if self.data['mailchimp_api'] is not None:
            mailchimp = Mailchimp(
                api_key=self.data['mailchimp_api'],
                datacenter=self.data['mailchimp_datacenter'],
                list_id=self.data['mailchimp_list_id'],
            )

            subject, body = self.get_data(content)

            # Carico su Mailchimp il post
            post_id, post_url = mailchimp.send(
                subject,
                body,
                self.img_url_get(),
                self.data
            )

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Mailchimp - post ID:", post_id)

            return post_id, post_url

    def get_channel_name(self, data):
        if data['mailchimp_api'] is not None:
            return "Mailchimp"

        if data['brevo_api'] is not None:
            return "Brevo"

    # def delete(self, post_id):
    #     if self.data['meta_page_id'] is not None:
    #         meta = Meta(page_id=self.data['meta_page_id'])
    #         return meta.ig_delete(post_id)
