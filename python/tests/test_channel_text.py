"""Testo del post adattato al canale: niente Markdown sui social, CTA fuori newsletter."""

from publisher.domain.channel_text import for_channel, html_to_text, markdown_to_text

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
    out = for_channel(ARTICLE, "facebook", _resolver, markdown=True)

    assert out.startswith("Novità di novembre\n\nArrivano i nuovi prodotti per l'inverno.")
    assert "#" not in out and "**" not in out and "*" not in out
    assert "- Giacche" not in out and "Giacche" in out
    assert "Guanti caldi (https://shop.test/guanti)" in out
    assert "https://blog.test/post-1" in out


def test_social_only_post_text_is_left_untouched():
    text = "Ciao a tutti! 🎉 *offerta* valida fino a domenica\n#saldi #inverno"

    for key in ("facebook", "instagram", "linkedin"):
        assert for_channel(text, key, _resolver, markdown=False) == text


def test_article_channels_keep_markdown():
    assert for_channel(ARTICLE, "wordpress", _resolver, markdown=True) == ARTICLE
    assert for_channel(ARTICLE, "newsletter", _resolver, markdown=True) == ARTICLE


def test_cta_on_social_becomes_text_and_url():
    text = 'Scopri di più\n\n[cta url WordPress id=7 text="Leggi l\'articolo"]'

    out = for_channel(text, "instagram", _resolver, markdown=True)

    assert "Leggi l'articolo: https://example.test/wordpress/7" in out
    assert "[cta" not in out


def test_cta_on_wordpress_becomes_plain_link():
    out = for_channel('[cta url="https://shop.test" text="Compra"]', "wordpress", _resolver, markdown=True)

    assert out == '<a href="https://shop.test">Compra</a>'


def test_cta_in_newsletter_is_left_to_the_account_template():
    text = '[cta url="https://shop.test" text="Compra"]'

    assert for_channel(text, "newsletter", _resolver, markdown=True) == text


def test_link_with_url_as_label_is_not_repeated():
    assert html_to_text('<p><a href="https://x.it">https://x.it</a></p>') == "https://x.it"


def test_markdown_to_text_handles_empty():
    assert markdown_to_text("") == ""
