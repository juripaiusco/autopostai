from typing import List
from task.reply.base import BaseReply


class InstagramReply(BaseReply):
    def __init__(self, data: List[any] = None, debug=False):
        super().__init__(channel_name="Instagram", data=data, debug=debug)

    def prompt_get(self):
        # Creo il prompt
        prompt = ""

        if self.data['ai_personality']:
            prompt = prompt + "Immedesimati in questa persona:\n" + self.data['ai_personality'] + "\n\n"

        if self.data['ai_prompt_prefix']:
            prompt = prompt + self.data['ai_prompt_prefix'] + "\n\n"

        if self.data['channel']:
            prompt = prompt + f"È stato creato questo post su {self.data['channel']}:\n"

        if self.data['ai_content']:
            prompt = prompt + self.data['ai_content'] + "\n"
            prompt = prompt + "\n"

        prompt = prompt + f"@{self.data['from_name']} ha risposto con un commento:"
        prompt = prompt + "\n"
        prompt = prompt + self.data['message'] + "\n"
        prompt = prompt + "\n"
        prompt = prompt + """
                            Impersonando la persona all'inizio, scrivi un risposta breve, positiva ed inclusiva,
                            che faccia felice chi la legge. Utilizza un tono informale.
                            """

        return prompt
