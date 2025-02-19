from task.posts.base import BasePost
import config as cfg
from datetime import datetime
from typing import List
import requests
from requests.auth import HTTPBasicAuth

class WordPressPost(BasePost):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="WordPress", data=data, debug=debug)

    def send(self, content):
        if self.data['wordpress_url'] is not None:
            auth = HTTPBasicAuth(self.data['wordpress_username'], self.data['wordpress_password'])

            data = {
                "title": self.data['title'],
                "content": content,
                "status": "publish"
            }

            response = requests.post(
                f"{self.data['wordpress_url']}/wp-json/wp/v2/posts",
                auth=auth,
                json=data
            )

            post_id = response.json().get("id")

            if self.debug:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "WordPress - post ID:", response.json().get("id"))

            return post_id
