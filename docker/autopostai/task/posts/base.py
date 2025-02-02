import config as cfg
from typing import List

class BasePost:
    def __init__(self, channel_name: str = '', data: List[any] = None, debug = False):
        self.channel_name = channel_name
        self.data = data
        self.debug = debug

    def data_set(self, data: List[any]):
        self.data = data

    def prompt_get(self):
        # Creo il prompt
        prompt = ""
        if self.data['ai_prompt_post'] is not None:
            prompt = f"{self.data['ai_prompt_post']}"

        return prompt

    def img_path_get(self):
        if self.data['img'] is not None:
            return f"./storage/app/public/posts/{self.data['id']}/{self.data['img']}"

    def img_url_get(self):
        if self.data['img'] is not None:
            return f"{cfg.URL}/storage/posts/{self.data['id']}/{self.data['img']}"

    def send(self, content):
        raise NotImplementedError("Devi implementare send_post nei canali specifici!")
