import config as cfg
from typing import List

class BaseReply:
    def __init__(self, channel_name: str = '', data: List[any] = None, debug=False):
        self.channel_name = channel_name
        self.data = data
        self.debug = debug
