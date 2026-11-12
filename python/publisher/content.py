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
from publisher.integrations import openai_text, shortcodes, web_pages
from publisher.domain import channel_text, media
from publisher.domain.channels import Channels, with_length_instruction
from publisher.publishing import PUBLISHERS
from publisher.publishing.base import ChannelPublisher


class EmptyContentError(Exception):
    """Il post non ha un testo da pubblicare: niente prompt, oppure l'LLM ha
    risposto vuoto. Il canale va in errore (con "Riprova") invece di uscire con
    le sole immagini; il testo vuoto non viene messo in cache."""


class ContentService:
    def __init__(self, post_repo: PostRepository, token_repo: TokenLogRepository):
        self.post_repo = post_repo
        self.token_repo = token_repo

    def resolve(self, post: dict, publisher: ChannelPublisher, url_resolver) -> str:
        # Contenuto gia' presente: riusalo, risolvendo solo gli shortcode.
        # Solo spazi/a capo = assente, si rigenera.
        if (post.get("ai_content") or "").strip():
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
        controllo del limite token: in v1 quel controllo vale solo per type='post'.

        Come v1, l'LLM riceve la conversazione del post (prompt + eventuale foto,
        poi il testo pubblicato) prima del commento: senza quel contesto vedeva
        solo foto + commento con un nome e rispondeva "non posso riconoscere le
        persone in foto". La foto sta nel messaggio del post, non in quello del
        commento."""
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

        context = []
        post_prompt = (comment.get("ai_prompt_post") or "").strip()
        if post_prompt or img_path:
            context.append({"role": "user", "content": openai_text.user_content(post_prompt, img_path)})
        published = self._published_social_text(comment)
        if published.strip():
            context.append({"role": "assistant", "content": published})

        text, tokens = openai_text.generate(
            api_key=comment["openai_api_key"],
            system_prompt=system_prompt,
            user_prompt=f"{prompt} Rispondi al commento.",
            context=context,
        )

        now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
        self.token_repo.log(comment["user_id"], "reply", comment["id"], tokens, now)
        return text

    @staticmethod
    def _published_social_text(comment: dict) -> str:
        """Il testo uscito sul social del commento: quello social del post se
        c'e', altrimenti il testo comune (l'HTML dell'editor in testo semplice,
        il Markdown va bene all'LLM cosi' com'e')."""
        social = channel_text.custom_social_text(comment)
        if social is not None:
            return social
        text = comment.get("ai_content") or ""
        return channel_text.html_to_text(text) if comment.get("ai_content_format") == "html" else text

    def _generate(self, post: dict, publisher: ChannelPublisher, url_resolver) -> str:
        if not (post.get("ai_prompt_post") or "").strip():
            raise EmptyContentError("Nessun testo né prompt AI per il post: aggiungi un prompt o un'anteprima e riprova.")

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

        # Il testo vale per tutti i canali: il prompt lo decide il canale primario
        # (WordPress > Newsletter), non il primo canale del ciclo; senza canale
        # primario va bene quello corrente (prompt base).
        channels = Channels.parse(post["channels"])
        text_key = channels.text_channel()
        prompt_publisher = PUBLISHERS[text_key](post) if text_key else publisher
        prompt = prompt_publisher.build_prompt()
        # Newsletter/WordPress: il modello non apre i link, FaPer3 gli passa il contenuto delle pagine.
        if getattr(prompt_publisher, "reads_urls", False):
            prompt = web_pages.enrich_prompt(prompt)

        text, tokens = openai_text.generate(
            api_key=post["openai_api_key"],
            system_prompt=system_prompt,
            # Limite caratteri (LinkedIn) dei canali accesi: il testo e' uno per tutti.
            user_prompt=with_length_instruction(prompt, channels.on_keys()),
            img_path=img_path,
        )

        now = datetime.now(config.LOCAL_TIMEZONE).strftime("%Y-%m-%d %H:%M:%S")
        self.token_repo.log(post["user_id"], "post", post["id"], tokens, now)

        if not text.strip():
            raise EmptyContentError("L'AI ha restituito un testo vuoto.")

        return shortcodes.parse_crosslinks(text, url_resolver)
