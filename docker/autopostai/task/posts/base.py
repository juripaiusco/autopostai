import json
import config as cfg
from typing import List
from PIL import Image

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

    def img_path_get(self, index = 0, get_all_img: bool = False):
        if self.data['img'] is not None:
            img_list = json.loads(self.data['img'])

            if not get_all_img:
                return f"./storage/app/public/posts/{self.data['id']}/{img_list[index]}"

            return [f"./storage/app/public/posts/{self.data['id']}/{img}" for img in img_list]
        else:
            return None

    # def img_path_get(self):
    #     if self.data['img'] is not None:
    #         return f"./storage/app/public/posts/{self.data['id']}/{self.data['img']}"

    def img_url_get(self, make_square: bool = False, get_all_img: bool = False):
        if self.data['img'] is not None:
            img_list = json.loads(self.data['img'])

            if make_square is True:
                img_square_names = []
                for index, img in enumerate(img_list):
                    img_square_name = f"square-{img}"
                    self.make_square(
                        self.img_path_get(index=index),
                        f"./storage/app/public/posts/{self.data['id']}/{img_square_name}"
                    )
                    img_square_names.append(img_square_name)

                if not get_all_img:
                    return f"{cfg.URL}/storage/posts/{self.data['id']}/{img_square_names[0]}"

                return [f"{cfg.URL}/storage/posts/{self.data['id']}/{img}" for img in img_square_names]

            if not get_all_img:
                return f"{cfg.URL}/storage/posts/{self.data['id']}/{img_list[0]}"

            return [f"{cfg.URL}/storage/posts/{self.data['id']}/{img}" for img in img_list]

    # def img_url_get(self, make_square: bool = False):
    #     if self.data['img'] is not None:
    #
    #         if make_square is True:
    #             img_square_name = f"square-{self.data['img']}"
    #             self.make_square(
    #                 self.img_path_get(),
    #                 f"./storage/app/public/posts/{self.data['id']}/{img_square_name}"
    #             )
    #             return f"{cfg.URL}/storage/posts/{self.data['id']}/{img_square_name}"
    #
    #         return f"{cfg.URL}/storage/posts/{self.data['id']}/{self.data['img']}"

    def make_square(self, image_path, output_path):
        image = Image.open(image_path)
        width, height = image.size

        if width == height:
            image.save(output_path)
            return

        # Determina il lato più lungo e crea un nuovo canvas quadrato
        new_size = max(width, height)
        new_image = Image.new("RGB", (new_size, new_size), (0, 0, 0))  # Sfondo Nero

        # Calcola le coordinate per centrare l'immagine
        x_offset = (new_size - width) // 2
        y_offset = (new_size - height) // 2
        new_image.paste(image, (x_offset, y_offset))

        new_image.save(output_path)

    def send(self, content):
        raise NotImplementedError("Devi implementare send_post nei canali specifici!")
