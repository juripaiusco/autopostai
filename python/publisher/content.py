"""Servizio di generazione/recupero del contenuto AI del post.

Unifica `ai_content_get` + `ai_generate` di v1. Il contenuto viene generato UNA
volta per post e messo in cache su `posts.ai_content`; i canali successivi
riusano lo stesso testo (comportamento di v1). Gli shortcode cross-link vengono
sempre risolti al volo (dipendono dallo stato di pubblicazione corrente).
"""

from __future__ import annotations

from datetime import datetime

from publisher import config
from publisher.db.repositories import PostRepository, TokenLogRepository
from publisher.integrations import openai_text, shortcodes
from publisher.domain import media
from publisher.publishing.base import ChannelPublisher


class ContentService:
    def __init__(self, post_repo: PostRepository, token_repo: TokenLogRepository):
        self.post_repo = post_repo
        self.token_repo = token_repo

    def resolve(self, post: dict, publisher: ChannelPublisher, url_resolver) -> str:
        # Contenuto gia' presente: riusalo, risolvendo solo gli shortcode.
        if post.get("ai_content"):
            return shortcodes.parse_crosslinks(post["ai_content"], url_resolver)

        if config.DRY_RUN:
            content = f"[DRY_RUN] contenuto simulato per il post {post['id']}"
        else:
            content = self._generate(post, publisher, url_resolver)

        # Cache su DB e in memoria, cosi' i canali successivi non rigenerano.
        self.post_repo.save_ai_content(post["id"], content)
        post["ai_content"] = content
        return content

    def generate_reply(self, comment: dict, prompt: str) -> str:
        """Genera il testo di una risposta a un commento. A differenza del contenuto
        del post NON viene messo in cache (ogni commento e' unico) e non passa dal
        controllo del limite token: in v1 quel controllo vale solo per type='post'."""
        if config.DRY_RUN:
            return f"[DRY_RUN] risposta simulata al commento {comment['id']}"

        system_prompt = " ".join(
            filter(
                None,
                [
                    comment.get("ai_personality"),
                    comment.get("ai_prompt_prefix"),
                    comment.get("ai_comment_prefix"),
                    comment.get("ai_prompt_comment"),
                    "Rispondi sempre solo con l'output richiesto, senza aggiungere altro.",
                ],
            )
        )

        # media.* si aspetta un dict con 'id' = id del POST (per il path su disco);
        # la riga commento espone quell'id come 'post_id'.
        post_ref = {"id": comment["post_id"], "img": comment.get("img")}
        img_path = None
        if comment.get("img_ai_check_on") == "1" and media.has_images(post_ref):
            img_path = media.local_paths(post_ref)[0]

        text, tokens = openai_text.generate(
            api_key=comment["openai_api_key"],
            system_prompt=system_prompt,
            user_prompt=prompt,
            img_path=img_path,
        )

        now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
        self.token_repo.log(comment["user_id"], "reply", comment["id"], tokens, now)
        return text

    def _generate(self, post: dict, publisher: ChannelPublisher, url_resolver) -> str:
        system_prompt = " ".join(
            filter(
                None,
                [
                    post.get("ai_personality"),
                    post.get("ai_prompt_prefix"),
                    "Rispondi sempre solo con l'output richiesto, senza aggiungere altro.",
                ],
            )
        )

        img_path = None
        if post.get("img_ai_check_on") == "1" and media.has_images(post):
            img_path = media.local_paths(post)[0]

        text, tokens = openai_text.generate(
            api_key=post["openai_api_key"],
            system_prompt=system_prompt,
            user_prompt=publisher.build_prompt(),
            img_path=img_path,
        )

        now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
        self.token_repo.log(post["user_id"], "post", post["id"], tokens, now)

        return shortcodes.parse_crosslinks(text, url_resolver)
