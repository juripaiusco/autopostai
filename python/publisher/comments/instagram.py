from __future__ import annotations

from datetime import datetime, timedelta

import pytz

from publisher import config
from publisher.comments.base import CommentFetcher, FetchedComment
from publisher.integrations.meta import Meta


class InstagramCommentFetcher(CommentFetcher):
    key = "instagram"

    def fetch(self) -> list[FetchedComment]:
        meta = Meta(page_id=self.post["meta_page_id"], user_access_token=config.META_USER_ACCESS_TOKEN)
        data = meta.ig_get_comments(self.remote_post_id)
        if data.get("error") is not None:
            return []

        return [
            FetchedComment(
                from_id=c.get("from", {}).get("id"),
                from_name=c.get("from", {}).get("username"),
                message_id=c["id"],
                message=c["text"],
                created_time=_normalize_date(c["timestamp"]),
            )
            for c in data.get("data", [])
        ]


def _normalize_date(raw: str) -> str:
    # Stessa correzione di +1h di Facebook (v1: timestamp Graph sfasato).
    date = datetime.strptime(raw, "%Y-%m-%dT%H:%M:%S%z") + timedelta(hours=1)
    return date.astimezone(pytz.UTC).strftime("%Y-%m-%d %H:%M:%S")
