"""Registry delle strategie di risposta automatica per-canale."""

from publisher.replies.base import ReplyChannel
from publisher.replies.facebook import FacebookReply
from publisher.replies.instagram import InstagramReply
from publisher.replies.linkedin import LinkedInReply

REPLIERS: dict[str, type[ReplyChannel]] = {
    FacebookReply.key: FacebookReply,
    InstagramReply.key: InstagramReply,
    LinkedInReply.key: LinkedInReply,
}

__all__ = ["REPLIERS", "ReplyChannel"]
