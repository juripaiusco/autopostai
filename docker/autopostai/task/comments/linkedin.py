import json
import pytz
import config as cfg
from typing import List
from services.linkedin import LinkedIn
from datetime import datetime, timedelta, timezone
from task.comments.base import BaseComment

class LinkedInComment(BaseComment):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="LinkedIn", data=data, debug=debug)

    def get(self):
        if self.data['linkedin_client_id'] is not None:
            channels = json.loads(self.data['channels'])

            linkedin = LinkedIn(
                client_id=self.data['linkedin_client_id'],
                client_secret=self.data['linkedin_client_secret'],
                token=self.data['linkedin_token'],
            )
            comments = linkedin.get_comments(channels['linkedin']['id'])

            if comments.get('elements') is not None:
                for comment in comments['elements']:

                    print(comment)

                    # 1. Converti in secondi
                    timestamp_in_seconds = comment['created']['time'] / 1000
                    # 2. Crea un datetime UTC timezone-aware
                    dt = datetime.fromtimestamp(timestamp_in_seconds, tz=timezone.utc)
                    # 3. Formatto nel formato ISO 8601 con timezone
                    date = dt.strftime('%Y-%m-%dT%H:%M:%S%z')

                    # Recupero il nome dell'autore del commento
                    linkedin = LinkedIn(
                        client_id=self.data['linkedin_client_id'],
                        client_secret=self.data['linkedin_client_secret'],
                        token=self.data['linkedin_token'],
                    )
                    author_name, author_surname = linkedin.get_author(comment.get('actor'))

                    self.save(
                        post_id=self.data['id'],
                        channel='linkedin',
                        from_id=comment.get('actor'),
                        from_name=f"{author_name} {author_surname}",
                        message_id=comment.get('$URN'),
                        message=comment.get('message').get('text'),
                        message_created_time=date
                    )

            if self.debug is True:
                print(
                    datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                    "LinkedIn commenti importati:",
                    len(comments.get('data', [])))
