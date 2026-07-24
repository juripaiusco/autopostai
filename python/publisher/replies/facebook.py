from __future__ import annotations

from publisher.integrations.meta import Meta
from publisher.replies.base import ReplyChannel, ReplyResult


class FacebookReply(ReplyChannel):
    key = "facebook"

    def build_prompt(self) -> str:
        author = self.comment.get("from_name")
        tail = f"e {author} ha risposto con questo commento: " if author else "ed e' stato risposto con questo commento: "
        return f"Il post e' stato creato su Facebook {tail}{self.comment['message']}"

    def send(self, reply_text: str) -> ReplyResult:
        meta = Meta(page_id=self.comment["meta_page_id"], user_access_token=self.comment["meta_token"])
        remote_id = meta.fb_reply_comment(self.comment["message_id"], reply_text)
        return ReplyResult(remote_id, reply_text)
