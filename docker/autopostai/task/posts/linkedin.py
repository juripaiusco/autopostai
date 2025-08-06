from task.posts.base import BasePost
import config as cfg
from typing import List
from services.linkedin import LinkedIn
from datetime import datetime

class LinkedInPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="LinkedIn", data=data, debug=debug)

    def send(self, content):
        if self.data['linkedin_client_id'] is not None:
            linkedin = LinkedIn(
                user_id=self.data['user_id'],
                client_id=self.data['linkedin_client_id'],
                client_secret=self.data['linkedin_client_secret'],
                token=self.data['linkedin_token'],
            )

            # Carico su LinkedIn il post CON l'immagine
            post_id, post_url = linkedin.send(content, self.img_path_get(get_all_img=True))

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "LinkedIn - post ID:", post_id)

            return post_id, post_url

    def delete(self, post_id):
        if self.data['linkedin_client_id'] is not None:
            linkedin = LinkedIn(
                user_id=self.data['user_id'],
                client_id=self.data['linkedin_client_id'],
                client_secret=self.data['linkedin_client_secret'],
                token=self.data['linkedin_token'],
            )

            post_id = linkedin.delete(post_id)

            return post_id
