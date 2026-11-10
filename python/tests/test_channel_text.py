"""Testo del post adattato al canale: niente Markdown sui social, CTA fuori newsletter."""

from publisher.domain.channel_text import for_channel, for_post_channel, html_to_text, markdown_to_text
from publisher.domain.channels import Channels
from publisher.publishing.base import split_content, split_html

ARTICLE = """# Novità di novembre

Arrivano i **nuovi prodotti** per l'*inverno*.

## Cosa trovi

- Giacche
- [Guanti caldi](https://shop.test/guanti)

Leggi anche https://blog.test/post-1
"""


def _resolver(post_id, channel_key, data_type):
    return f"https://example.test/{channel_key}/{post_id}"


def test_social_gets_plain_text_from_markdown_article():
    out = for_channel(ARTICLE, "facebook", _resolver, "markdown")

    assert out.startswith("Novità di novembre\n\nArrivano i nuovi prodotti per l'inverno.")
    assert "#" not in out and "**" not in out and "*" not in out
    assert "- Giacche" not in out and "Giacche" in out
    assert "Guanti caldi (https://shop.test/guanti)" in out
    assert "https://blog.test/post-1" in out


def test_social_only_post_text_is_left_untouched():
    text = "Ciao a tutti! 🎉 *offerta* valida fino a domenica\n#saldi #inverno"

    for key in ("facebook", "instagram", "linkedin"):
        assert for_channel(text, key, _resolver, "text") == text


def test_article_channels_keep_markdown():
    assert for_channel(ARTICLE, "wordpress", _resolver, "markdown") == ARTICLE
    assert for_channel(ARTICLE, "newsletter", _resolver, "markdown") == ARTICLE


def test_cta_on_social_becomes_text_and_url():
    text = 'Scopri di più\n\n[cta url WordPress id=7 text="Leggi l\'articolo"]'

    out = for_channel(text, "instagram", _resolver, "markdown")

    assert "Leggi l'articolo: https://example.test/wordpress/7" in out
    assert "[cta" not in out


def test_cta_on_wordpress_becomes_plain_link():
    out = for_channel('[cta url="https://shop.test" text="Compra"]', "wordpress", _resolver, "markdown")

    assert out == '<a href="https://shop.test">Compra</a>'


def test_cta_in_newsletter_is_left_to_the_account_template():
    text = '[cta url="https://shop.test" text="Compra"]'

    assert for_channel(text, "newsletter", _resolver, "markdown") == text


def test_link_with_url_as_label_is_not_repeated():
    assert html_to_text('<p><a href="https://x.it">https://x.it</a></p>') == "https://x.it"


def test_markdown_to_text_handles_empty():
    assert markdown_to_text("") == ""


EDITOR_HTML = (
    '<h1>Novità di novembre</h1>'
    '<p style="text-align:center;">Arrivano i <strong>nuovi prodotti</strong> '
    '<span style="color:#c0392b;">in rosso</span>.</p>'
    '<ul><li><p>Giacche</p></li></ul>'
    '<p><a href="https://shop.test/guanti">Guanti</a></p>'
)

ARTICLE_AND_SOCIAL = Channels.parse({"facebook": {"on": True}, "wordpress": {"on": True}})
SOCIAL_ONLY = Channels.parse({"facebook": {"on": True}})


def test_social_gets_plain_text_from_editor_html():
    out = for_channel(EDITOR_HTML, "linkedin", _resolver, "html")

    assert out.startswith("Novità di novembre\n\nArrivano i nuovi prodotti in rosso.")
    assert "<" not in out and "style" not in out
    assert "Giacche" in out and "Guanti (https://shop.test/guanti)" in out


def test_custom_social_text_wins_and_resolves_crosslinks():
    post = {"ai_content_format": "html", "ai_content_social": "Leggi qui 👉 [WordPress url id=5]"}

    out = for_post_channel(post, ARTICLE_AND_SOCIAL, "facebook", EDITOR_HTML, _resolver)

    assert out == "Leggi qui 👉 https://example.test/wordpress/5"


def test_custom_social_text_is_not_used_for_the_article():
    post = {"ai_content_format": "html", "ai_content_social": "solo social"}

    assert for_post_channel(post, ARTICLE_AND_SOCIAL, "wordpress", EDITOR_HTML, _resolver) == EDITOR_HTML


def test_without_custom_text_social_is_derived_from_the_post_format():
    post = {"ai_content_format": "html", "ai_content_social": "  "}

    out = for_post_channel(post, ARTICLE_AND_SOCIAL, "instagram", EDITOR_HTML, _resolver)

    assert out.startswith("Novità di novembre")
    assert "<" not in out


def test_social_only_post_is_never_converted_whatever_the_column_says():
    text = "Promo *weekend* 🎉"
    post = {"ai_content_format": "markdown", "ai_content_social": None}

    assert for_post_channel(post, SOCIAL_ONLY, "facebook", text, _resolver) == text


def test_split_html_takes_the_first_h1_as_title():
    assert split_html(EDITOR_HTML)[0] == "Novità di novembre"
    assert split_html(EDITOR_HTML)[1].startswith('<p style="text-align:center;">')
    assert split_html("<p>senza titolo</p>") == ("", "<p>senza titolo</p>")


def test_split_content_keeps_markdown_behaviour():
    title, body = split_content("# Titolo\n\n**Corpo**", "markdown")

    assert title == "Titolo"
    assert body == "<p><strong>Corpo</strong></p>"
