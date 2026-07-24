from __future__ import annotations

from publisher.domain import media
from publisher.domain.channels import Channels
from publisher.integrations.wordpress import WordPress
from publisher.publishing.base import ChannelPublisher, PublishResult, split_markdown


class WordPressPublisher(ChannelPublisher):
    key = "wordpress"

    def build_prompt(self) -> str:
        prompt = (
            "Generami un articolo per wordpress formattato in Markdown, "
            "con un titolo (#), sottotitoli (##), elenchi e grassetto (**bold**). "
            "Non usare ```markdown all'inizio e ``` alla fine."
        )
        if self.post.get("ai_prompt_post"):
            prompt = f"{prompt} {self.post['ai_prompt_post']}"
        return prompt

    def publish(self, content: str) -> PublishResult:
        title, body = split_markdown(content)

        # Categorie: prima quelle selezionate nel canale, altrimenti il default account.
        entry = Channels.parse(self.post["channels"]).entry("wordpress")
        category_ids = entry.wordpress_category_ids() if entry else []
        if not category_ids and self.post.get("wordpress_cat_id"):
            category_ids = [self.post["wordpress_cat_id"]]

        wp = WordPress(
            url=self.post["wordpress_url"],
            username=self.post["wordpress_username"],
            password=self.post["wordpress_password"],
        )
        post_id, url, gallery_html = wp.publish(
            title=title,
            content=body,
            image_paths=media.local_paths(self.post) if media.has_images(self.post) else [],
            category_ids=category_ids,
        )
        return PublishResult(post_id, url, gallery_html)
