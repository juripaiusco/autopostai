from task.posts.base import BasePost
import config as cfg
from typing import List
from services.meta import Meta
from datetime import datetime

class WordPressPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="WordPress", data=data, debug=debug)

    def send(self, content):
        # if self.data['meta_page_id'] is not None:
        #     meta = Meta(page_id=self.data['meta_page_id'])
        #
        #     if self.data['img']:
        #         # Carico su WordPress il post CON l'immagine
        #         post_id = meta.fb_generate_post(content, self.img_path_get())
        #     else:
        #         # Carico su WordPress il post SENZA l'immagine
        #         post_id = meta.fb_generate_post(content)
        #
        #     if self.debug:
        #         print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "WordPress - post ID:", post_id)
        #
        #     return post_id
        return None
