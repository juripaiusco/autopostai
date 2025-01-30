import config as cfg
from typing import List
from services.mysql import Mysql

class BaseComment:
    def __init__(self, channel_name: str = '', data: List[any] = None, debug = False):
        self.channel_name = channel_name
        self.data = data
        self.debug = debug

    def save(self,
             post_id: int,
             channel: str = '',
             from_id: int = 0,
             from_name: str = '',
             message_id: int = 0,
             message: str = '',
             message_created_time: str = None,
             ):

        mysql = Mysql()
        mysql.connect()

        rows = mysql.query(f"""
                    SELECT 1
                        FROM {cfg.DB_PREFIX}comments

                    WHERE post_id = '{post_id}'
                        AND channel = '{channel}'
                        AND message_id = '{message_id}'
                """)

        if not rows:
            mysql.query(f"""
                            INSERT IGNORE INTO {cfg.DB_PREFIX}comments (
                                post_id,
                                channel,
                                from_id,
                                from_name,
                                message_id,
                                message,
                                message_created_time
                            ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, (
                post_id,
                channel,
                from_id,
                from_name,
                message_id,
                message,
                message_created_time
            ))

        mysql.close()
