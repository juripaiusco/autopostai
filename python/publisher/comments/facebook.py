from __future__ import annotations

from datetime import datetime, timedelta

import pytz

from publisher.comments.base import CommentFetcher, FetchedComment
from publisher.integrations.meta import Meta


class FacebookCommentFetcher(CommentFetcher):
    key = "facebook"

    def fetch(self) -> list[FetchedComment]:
        meta = Meta(page_id=self.post["meta_page_id"], user_access_token=self.post["meta_token"])
        data = meta.fb_get_comments(self.remote_post_id)
        if data.get("error") is not None:
            return []

        return [
            FetchedComment(
                from_id=c.get("from", {}).get("id"),
                from_name=c.get("from", {}).get("name"),
                message_id=c["id"],
                message=c["message"],
                created_time=_normalize_date(c["created_time"]),
            )
            for c in data.get("data", [])
        ]


def _normalize_date(raw: str) -> str:
    # La Graph API restituisce un orario sfasato di un'ora: correzione ereditata
    # da v1 (verificata all'epoca contro i dati reali di produzione).
    date = datetime.strptime(raw, "%Y-%m-%dT%H:%M:%S%z") + timedelta(hours=1)
    return date.astimezone(pytz.UTC).strftime("%Y-%m-%d %H:%M:%S")
