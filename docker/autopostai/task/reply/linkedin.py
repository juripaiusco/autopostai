from typing import List
from task.reply.base import BaseReply


class LinkedInReply(BaseReply):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name="LinkedIn", data=data, debug=debug)

    def prompt_get(self):
        # Creo il prompt
        prompt = ""

        if self.data['channel']:
            prompt = prompt + f"Il post è stato creato su {self.data['channel']} "

        if self.data['from_name'] is not None:
            prompt = prompt + f"e {self.data['from_name']} ha risposto con questo commento: "
        else:
            prompt = prompt + f"e questa è la risposta al commento: "

        # prompt = prompt + f"ed è stato risposto con questo commento: "
        prompt = prompt + self.data['message']

        # if self.data['from_name'] is not None:
        #     prompt = prompt + f""" quando rispondi al commento, non inserire il nome {self.data['from_name']} """

        return prompt
