from __future__ import annotations

from publisher.domain import media
from publisher.integrations.meta import Meta
from publisher.publishing.base import ChannelPublisher, PublishResult


class InstagramPublisher(ChannelPublisher):
    key = "instagram"

    def publish(self, content: str) -> PublishResult:
        meta = Meta(page_id=self.post["meta_page_id"], user_access_token=self.post["meta_token"])
        # Instagram richiede immagini quadrate.
        image_urls = media.public_urls(self.post, make_square=True)
        post_id, url = meta.ig_publish(content, image_urls)
        return PublishResult(post_id, url)
