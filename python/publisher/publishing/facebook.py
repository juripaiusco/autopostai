from __future__ import annotations

from publisher import config
from publisher.domain import media
from publisher.integrations.meta import Meta
from publisher.publishing.base import ChannelPublisher, PublishResult


class FacebookPublisher(ChannelPublisher):
    key = "facebook"

    def publish(self, content: str) -> PublishResult:
        meta = Meta(page_id=self.post["meta_page_id"], user_access_token=config.META_USER_ACCESS_TOKEN)
        image_urls = media.public_urls(self.post) if media.has_images(self.post) else None
        post_id, url = meta.fb_publish(content, image_urls)
        return PublishResult(post_id, url)
