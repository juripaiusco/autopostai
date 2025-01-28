import config as cfg
from typing import List

class BaseReply:
    def __init__(self, channel_name: str = '', data: List[any] = None, debug=False):
        self.channel_name = channel_name
        self.data = data
        self.debug = debug

    def img_path_get(self):
        if self.data['img'] is not None:
            return f"./storage/app/public/posts/{self.data['id']}/{self.data['img']}"

    def img_url_get(self):
        if self.data['img'] is not None:
            return f"{cfg.URL}/storage/posts/{self.data['id']}/{self.data['img']}"
