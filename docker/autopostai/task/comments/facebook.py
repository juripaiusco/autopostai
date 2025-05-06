import json
import pytz
import config as cfg
from typing import List
from services.meta import Meta
from datetime import datetime, timedelta
from task.comments.base import BaseComment

class FacebookComment(BaseComment):
    def __init__(self, data: List[any] = None, debug = False):
        super().__init__(channel_name="Facebook", data=data, debug=debug)

    def get(self):
        if self.data['meta_page_id'] is not None:
            channels = json.loads(self.data['channels'])

            meta = Meta(page_id=self.data['meta_page_id'])
            comments = meta.fb_get_comments(channels['facebook']['id'])

            if comments.get('error') is None:
                for comment in comments['data']:

                    # Converto la data di Facebook perché non è corretta
                    raw_date = comment['created_time']
                    facebook_date = datetime.strptime(raw_date, '%Y-%m-%dT%H:%M:%S%z')
                    original_date = facebook_date + timedelta(hours=1)
                    utc_date = original_date.astimezone(pytz.UTC)
                    converted_date = utc_date.strftime('%Y-%m-%d %H:%M:%S')

                    self.save(
                        post_id=self.data['id'],
                        channel='facebook',
                        from_id=comment.get('from', {}).get('id'),
                        from_name=comment.get('from', {}).get('name'),
                        message_id=comment['id'],
                        message=comment['message'],
                        message_created_time=converted_date
                    )

            if self.debug is True:
                print(
                    datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                    "Facebook commenti importati:",
                    len(comments.get('data', [])))
