from task.posts.base import BasePost
import config as cfg
from typing import List
from services.meta import Meta
from datetime import datetime

class FacebookPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="Facebook", data=data, debug=debug)

    def send(self, content):
        if self.data['meta_page_id'] is not None:
            meta = Meta(page_id=self.data['meta_page_id'])

            if self.data['img']:
                # Carico su Facebook il post CON l'immagine
                post_id = meta.fb_generate_post(content, self.img_url_get(get_all_img=True))
            else:
                # Carico su Facebook il post SENZA l'immagine
                post_id = meta.fb_generate_post(content)

            post_url = f"https://www.facebook.com/{self.data['meta_page_id']}/posts/{post_id}"

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Facebook - post ID:", post_id)

            return post_id, post_url

    def delete(self, post_id):
        if self.data['meta_page_id'] is not None:
            meta = Meta(page_id=self.data['meta_page_id'])
            return meta.fb_delete(post_id)
