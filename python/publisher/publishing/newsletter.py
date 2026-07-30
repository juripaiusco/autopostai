from __future__ import annotations

from publisher.domain import media
from publisher.domain.channels import Channels
from publisher.integrations import shortcodes
from publisher.integrations.brevo import Brevo
from publisher.integrations.mailchimp import Mailchimp
from publisher.publishing.base import ChannelPublisher, PublishResult, split_markdown


class NewsletterPublisher(ChannelPublisher):
    """Pubblica la newsletter via Mailchimp o Brevo.

    Il provider e la lista arrivano dal canale (`newsletter.list.provider/id`);
    se manca la lista si usa il default dell'account. Template e CTA sono unificati
    a livello account (`nl_template` / `nl_template_cta`).

    smtp_custom NON passa da publish() (una singola chiamata = un id, non
    adatto a un invio spalmato su piu' contatti/run): publisher/tasks/
    newsletter_send.py usa solo render() per ottenere oggetto/html, poi manda
    da se' via SmtpClient — vedi domain/channels.py: ChannelEntry.needs_publish
    esclude esplicitamente smtp_custom dal ciclo generico.
    """

    key = "newsletter"

    def build_prompt(self) -> str:
        prompt = (
            "Generami una mail per una newsletter formattata in Markdown, "
            "con un titolo (#), sottotitoli (##), ed eventualmente elenchi e grassetto (**bold**). "
            "Non usare ```markdown all'inizio e ``` alla fine."
        )
        if self.post.get("ai_prompt_post"):
            prompt = f"{prompt} {self.post['ai_prompt_post']}"
        return prompt

    def _assemble_html(self, body_html: str) -> str:
        """Inietta l'immagine, avvolge nel template account, espande gli shortcode CTA."""
        image_urls = media.public_urls(self.post)
        if image_urls:
            body_html = (
                f'<img src="{image_urls[0]}" style="width:100%; max-width:600px; height:auto; '
                f'display:block; margin: 0 auto;"><br>{body_html}'
            )
        html = (self.post.get("nl_template") or "[content]").replace("[content]", body_html)
        resolver = self.url_resolver or (lambda *_: "")
        return shortcodes.parse_cta(html, self.post.get("nl_template_cta") or "", resolver)

    def render(self, content: str) -> tuple[str, str]:
        """Oggetto + html pronti (template account, immagine, CTA espansa).
        Punto di ingresso condiviso fra publish() (mailchimp/brevo) e
        newsletter_send.py (smtp_custom)."""
        subject, body = split_markdown(content)
        return subject, self._assemble_html(body)

    def publish(self, content: str) -> PublishResult:
        subject, html = self.render(content)

        entry = Channels.parse(self.post["channels"]).entry("newsletter")
        provider = (entry.newsletter_provider() if entry else None) or self._infer_provider()
        list_id = entry.newsletter_list_id() if entry else None

        if provider == "mailchimp":
            client = Mailchimp(self.post["nl_mailchimp_api"], self.post["nl_mailchimp_datacenter"])
            post_id, url = client.send(
                subject, html,
                self.post["nl_mailchimp_from_name"], self.post["nl_mailchimp_from_email"],
                list_id or self.post["nl_mailchimp_list_id"],
            )
            return PublishResult(post_id, url)

        if provider == "brevo":
            client = Brevo(self.post["nl_brevo_api"])
            list_ids = [int(list_id)] if list_id else [int(self.post["nl_brevo_list_id"])]
            post_id, url = client.send(
                subject, html,
                self.post["nl_brevo_from_name"], self.post["nl_brevo_from_email"], list_ids,
            )
            return PublishResult(post_id, url)

        raise ValueError(f"Nessun provider newsletter configurato per il post {self.post['id']}")

    def _infer_provider(self) -> str | None:
        """Fallback se il canale non indica il provider: quello con la chiave API impostata."""
        if self.post.get("nl_mailchimp_api"):
            return "mailchimp"
        if self.post.get("nl_brevo_api"):
            return "brevo"
        return None
