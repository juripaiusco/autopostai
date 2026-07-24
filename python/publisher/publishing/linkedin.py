from __future__ import annotations

from publisher.domain import media
from publisher.integrations.linkedin import LinkedIn
from publisher.publishing.base import ChannelPublisher, PublishResult


class LinkedInPublisher(ChannelPublisher):
    key = "linkedin"

    def publish(self, content: str) -> PublishResult:
        linkedin = LinkedIn(
            token=self.post["linkedin_token"],
            company_id=self.post["linkedin_company_id"],
        )
        post_id, url = linkedin.publish(content, media.local_paths(self.post))
        return PublishResult(post_id, url)
