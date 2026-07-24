"""Registry delle strategie di pubblicazione per-canale.

Aggiungere un canale = aggiungere una classe ChannelPublisher e una riga qui.
"""

from publisher.publishing.base import ChannelPublisher, PublishResult
from publisher.publishing.facebook import FacebookPublisher
from publisher.publishing.instagram import InstagramPublisher
from publisher.publishing.linkedin import LinkedInPublisher
from publisher.publishing.newsletter import NewsletterPublisher
from publisher.publishing.wordpress import WordPressPublisher

PUBLISHERS: dict[str, type[ChannelPublisher]] = {
    FacebookPublisher.key: FacebookPublisher,
    InstagramPublisher.key: InstagramPublisher,
    LinkedInPublisher.key: LinkedInPublisher,
    WordPressPublisher.key: WordPressPublisher,
    NewsletterPublisher.key: NewsletterPublisher,
}

__all__ = ["PUBLISHERS", "ChannelPublisher", "PublishResult"]
