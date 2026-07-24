from __future__ import annotations

from datetime import datetime, timezone

from publisher.comments.base import CommentFetcher, FetchedComment
from publisher.integrations.linkedin import LinkedIn


class LinkedInCommentFetcher(CommentFetcher):
    key = "linkedin"

    def fetch(self) -> list[FetchedComment]:
        linkedin = LinkedIn(token=self.post["linkedin_token"], company_id=self.post["linkedin_company_id"])
        data = linkedin.get_comments(self.remote_post_id)
        elements = data.get("elements")
        if not elements:
            return []

        out = []
        for c in elements:
            created = datetime.fromtimestamp(c["created"]["time"] / 1000, tz=timezone.utc)
            first, last = linkedin.get_author(c.get("actor"))
            out.append(
                FetchedComment(
                    from_id=c.get("actor"),
                    from_name=f"{first or ''} {last or ''}".strip() or None,
                    message_id=c.get("$URN"),
                    message=c.get("message", {}).get("text", ""),
                    created_time=created.strftime("%Y-%m-%d %H:%M:%S"),
                )
            )
        return out
