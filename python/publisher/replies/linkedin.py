from __future__ import annotations

from publisher.integrations.linkedin import LinkedIn
from publisher.replies.base import ReplyChannel, ReplyResult


class LinkedInReply(ReplyChannel):
    key = "linkedin"

    def build_prompt(self) -> str:
        author = self.comment.get("from_name")
        tail = f"e {author} ha risposto con questo commento: " if author else "e questa e' la risposta al commento: "
        return f"Il post e' stato creato su LinkedIn {tail}{self.comment['message']}"

    def send(self, reply_text: str) -> ReplyResult:
        linkedin = LinkedIn(token=self.comment["linkedin_token"], company_id=self.comment["linkedin_company_id"])
        # LinkedIn puo' riscrivere il testo per anteporre il nome dell'autore
        # quando serve per posizionare la @mention (vedi LinkedIn.reply_comment).
        remote_id, sent_text = linkedin.reply_comment(
            comment_urn=self.comment["message_id"],
            actor_urn=self.comment["from_id"],
            actor_name=self.comment.get("from_name") or "",
            reply_message=reply_text,
        )
        return ReplyResult(remote_id, sent_text)
