from typing import List
from task.reply.base import BaseReply


class FacebookReply(BaseReply):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name="Facebook", data=data, debug=debug)

    def prompt_get(self):
        # Creo il prompt
        prompt = ""

        if self.data['channel']:
            prompt = prompt + f"È stato creato questo post su {self.data['channel']} "

        if self.data['from_name'] is not None:
            prompt = prompt + f"e {self.data['from_name']} ha risposto con questo commento:"
        else:
            prompt = prompt + f"ed è stato risposto con questo commento:"

        prompt = prompt + f"\n{self.data['message']}"

        return prompt
