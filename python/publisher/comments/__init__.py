"""Registry delle strategie di fetch commenti per-canale (solo social)."""

from publisher.comments.base import CommentFetcher, FetchedComment
from publisher.comments.facebook import FacebookCommentFetcher
from publisher.comments.instagram import InstagramCommentFetcher
from publisher.comments.linkedin import LinkedInCommentFetcher

FETCHERS: dict[str, type[CommentFetcher]] = {
    FacebookCommentFetcher.key: FacebookCommentFetcher,
    InstagramCommentFetcher.key: InstagramCommentFetcher,
    LinkedInCommentFetcher.key: LinkedInCommentFetcher,
}

__all__ = ["FETCHERS", "CommentFetcher", "FetchedComment"]
