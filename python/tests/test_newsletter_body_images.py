"""Unit test: le immagini nel corpo della newsletter (es. prodotti presi dalle
pagine linkate nel prompt) escono con max-width, cosi' i client di posta non le
allargano oltre la mail. Niente DB, niente rete."""

from publisher.publishing.newsletter import NewsletterPublisher


def _render(content):
    post = {"img": None, "title": "T", "ai_content_format": "html", "nl_template": "<div>[content]</div>"}
    return NewsletterPublisher(post).render(content)


def test_body_images_get_a_max_width_style():
    _, html = _render('<h1>Novità</h1><p>Scarpa</p><img src="https://shop.test/x.jpg" alt="Scarpa">')

    assert '<img style="max-width:100%; height:auto; display:block; margin: 0 auto;" src="https://shop.test/x.jpg" alt="Scarpa">' in html


def test_body_images_with_their_own_style_are_left_alone():
    _, html = _render('<h1>Novità</h1><img style="width:120px" src="https://shop.test/x.jpg">')

    assert '<img style="width:120px" src="https://shop.test/x.jpg">' in html


def test_markdown_images_are_styled_too():
    post = {"img": None, "title": "T", "ai_content_format": None, "nl_template": "[content]"}
    _, html = NewsletterPublisher(post).render("# Novità\n\n![Scarpa](https://shop.test/x.jpg)")

    assert 'style="max-width:100%' in html and 'src="https://shop.test/x.jpg"' in html


def _render_with_images(template, monkeypatch):
    monkeypatch.setattr(
        "publisher.publishing.newsletter.media.public_urls",
        lambda post: ["https://app.test/1.jpg", "https://app.test/2.jpg"],
    )
    post = {"img": None, "title": "T", "ai_content_format": "html", "nl_template": template}
    return NewsletterPublisher(post).render("<h1>Oggetto</h1><p>Corpo</p>")


def test_template_image_shortcodes_place_post_images(monkeypatch):
    _, html = _render_with_images("<div>[image 2]</div>[content]", monkeypatch)

    assert '<div><img src="https://app.test/2.jpg"' in html
    # il template gestisce le immagini: la prima non viene anteposta al corpo
    assert "https://app.test/1.jpg" not in html


def test_without_image_shortcodes_the_first_image_is_prepended_as_before(monkeypatch):
    _, html = _render_with_images("<div>[content]</div>", monkeypatch)

    assert '<div><img src="https://app.test/1.jpg"' in html


def test_template_without_content_placeholder_ignores_the_text(monkeypatch):
    _, html = _render_with_images("<html><body>Solo mio [image 1]</body></html>", monkeypatch)

    assert "Corpo" not in html
    assert 'src="https://app.test/1.jpg"' in html
