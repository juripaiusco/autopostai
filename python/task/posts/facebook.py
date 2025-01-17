from task.posts.base import BasePost
import json
import config as cfg
from typing import List
from services.meta import Meta
from datetime import datetime
from services.mysql import Mysql

class FacebookPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="Facebook", data=data, debug=debug)

    def send(self, content):
        # Trasformo in array il json dei canali e poi lo seleziono per recuperare l'ID del post
        channels = json.loads(self.data['channels'])

        meta = Meta(page_id=self.data['meta_page_id'])

        if self.data['img']:
            # Carico su Facebook il post CON l'immagine
            channels['facebook']['id'] = meta.fb_generate_post(content, self.img_path_get())
        else:
            # Carico su Facebook il post SENZA l'immagine
            channels['facebook']['id'] = meta.fb_generate_post(content)

        mysql = Mysql()
        mysql.connect()

        # Salvo l'ID del post
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET channels = %s WHERE id = %s",
            parameters=(json.dumps(channels), self.data['id'])
        )

        if self.debug:
            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Facebook - post ID:", channels['facebook']['id'])

        mysql.close()

        return channels['facebook']['id']
