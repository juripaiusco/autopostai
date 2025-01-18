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
            # Trasformo in array il json dei canali e poi lo seleziono per recuperare l'ID del post
            channels = json.loads(self.data['channels'])

            meta = Meta(page_id=self.data['meta_page_id'])

            # Carico su Instagram il post
            channels['instagram']['id'] = meta.ig_generate_post(content, self.img_url_get())

            mysql = Mysql()
            mysql.connect()

            # Salvo l'ID del post
            mysql.query(
                query=f"UPDATE {cfg.DB_PREFIX}posts SET channels = %s WHERE id = %s",
                parameters=(json.dumps(channels), self.data['id'])
            )

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Instagram - post ID:", channels['instagram']['id'])

            mysql.close()

            return channels['instagram']['id']
