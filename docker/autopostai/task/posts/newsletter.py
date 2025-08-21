import json
from services.brevo import Brevo
from task.posts.base import BasePost
import config as cfg
from typing import List
from services.mailchimp import Mailchimp
from datetime import datetime
import markdown


class NewsletterPost(BasePost):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name=self.channel_name_get(data), data=data, debug=debug)

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
        # ------------------------------------------------------- #
        #                         MAILCHIMP                       #
        # ------------------------------------------------------- #
        if self.data['mailchimp_api'] is not None:
            mailchimp = Mailchimp(
                api_key=self.data['mailchimp_api'],
                datacenter=self.data['mailchimp_datacenter'],
                list_id=self.lists_get(),
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

        # - END -----------------------------------------------------------------------------

        # ------------------------------------------------------- #
        #                          BREVO                          #
        # ------------------------------------------------------- #

        if self.data['brevo_api'] is not None:
            brevo = Brevo(
                api_key=self.data['brevo_api'],
                list_id=self.lists_get(),
            )

            subject, body = self.get_data(content)

            # Carico su Brevo il post
            post_id, post_url = brevo.send(
                subject,
                body,
                self.img_url_get(),
                self.data
            )

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Brevo - post ID:", post_id)

            return post_id, post_url

        # - END -----------------------------------------------------------------------------

    def channel_name_get(self, data):
        if data.get('mailchimp_api', None) is not None:
            return "Mailchimp"

        if data.get('brevo_api', None) is not None:
            return "Brevo"

    # Recupero l'ID o gli ID delle lista a cui inviare la newsletter
    def lists_get(self):
        # MailChimp può inviare ad una singola lista per volta,
        # quindi lists_id non è un array
        if self.data['mailchimp_api'] is not None:
            lists_id = ""

            if self.data['mailchimp_list_id'] is not None:
                lists_id = self.data['mailchimp_list_id']

            if self.data['channels'] is not None:
                channels = json.loads(self.data['channels'])
                lists = channels.get('newsletter', {}).get('options', {}).get('lists', {}).get('lists', [])
                lists_id_new = ""

                for list in lists:
                    if list['on'] == '1':
                        lists_id_new = list['id']

                if lists_id_new:
                    lists_id = lists_id_new

        # Brevo può inviare a più liste contemporaneamente,
        # quindi lists_id sarà un array
        if self.data['brevo_api'] is not None:
            lists_id = []

            if self.data['brevo_list_id'] is not None:
                lists_id = [int(self.data['brevo_list_id'])]

            if self.data['channels'] is not None:
                channels = json.loads(self.data['channels'])
                lists = channels.get('newsletter', {}).get('options', {}).get('lists', {}).get('lists', [])
                lists_id_new = []

                for list in lists:
                    if list['on'] == '1':
                        lists_id_new.append(int(list['id']))

                if lists_id_new:
                    lists_id = lists_id_new

        return lists_id

    def delete(self, post_id):
        if self.data['mailchimp_api'] is not None:
            mailchimp = Mailchimp(
                api_key=self.data['mailchimp_api'],
                datacenter=self.data['mailchimp_datacenter'],
                list_id=self.data['mailchimp_list_id'],
            )
            return mailchimp.delete(post_id)

        if self.data['brevo_api'] is not None:
            return post_id

    def save_url(self, post_id):
        if self.data['brevo_api'] is not None:
            brevo = Brevo(
                api_key=self.data['brevo_api'],
                list_id=None
            )
            return brevo.save_url(post_id, self.data)
