from __future__ import annotations

from publisher import config
from publisher.integrations.meta import Meta
from publisher.replies.base import ReplyChannel, ReplyResult


class InstagramReply(ReplyChannel):
    key = "instagram"

    def build_prompt(self) -> str:
        author = self.comment.get("from_name")
        tail = f"e @{author} ha risposto con questo commento: " if author else "ed e' stato risposto con questo commento: "
        return f"Il post e' stato creato su Instagram {tail}{self.comment['message']}"

    def send(self, reply_text: str) -> ReplyResult:
        meta = Meta(page_id=self.comment["meta_page_id"], user_access_token=config.META_USER_ACCESS_TOKEN)
        remote_id = meta.ig_reply_comment(self.comment["message_id"], reply_text)
        return ReplyResult(remote_id, reply_text)
