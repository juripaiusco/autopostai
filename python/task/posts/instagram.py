from task.posts.base import BasePost
import json
import config as cfg
from typing import List
from services.meta import Meta
from datetime import datetime
from services.mysql import Mysql

class InstagramPost(BasePost):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name="Instagram", data=data, debug=debug)

    def send(self, content):
        if self.data['meta_page_id'] is not None:
            meta = Meta(page_id=self.data['meta_page_id'])

            # Carico su Instagram il post
            post_id = meta.ig_generate_post(content, self.img_url_get())

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Instagram - post ID:", post_id)

            return post_id
