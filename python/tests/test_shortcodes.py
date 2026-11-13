"""Unit test del parsing shortcode (cross-link e CTA)."""

from publisher.integrations import shortcodes


def _resolver(post_id, channel_key, data_type):
    return f"https://example.test/{channel_key}/{post_id}"


def test_parse_crosslinks_replaces_url_shortcode():
    out = shortcodes.parse_crosslinks("Vedi [WordPress url id=42] ora", _resolver)
    assert out == "Vedi https://example.test/wordpress/42 ora"


def test_parse_crosslinks_ignores_non_url_datatype():
    text = "Nulla [Facebook id id=1] qui"
    assert shortcodes.parse_crosslinks(text, _resolver) == text


def test_parse_crosslinks_unknown_channel_left_untouched():
    text = "[Tiktok url id=1]"
    assert shortcodes.parse_crosslinks(text, _resolver) == text


def test_parse_cta_expands_template_with_text_and_resolved_url():
    template = '<a href="[url]">[text]</a>'
    out = shortcodes.parse_cta('[cta url Facebook id=7 text="Scopri"]', template, _resolver)
    assert out == '<a href="https://example.test/facebook/7">Scopri</a>'


def test_crosslink_with_quoted_id_is_the_current_format():
    out = shortcodes.parse_crosslinks('Vedi [WordPress url id="123"] e [Facebook url id=\'7\']', _resolver)
    assert out == "Vedi https://example.test/wordpress/123 e https://example.test/facebook/7"


def test_crosslink_inside_editor_html_href():
    html = '<a href="[WordPress url id=&quot;123&quot;]">articolo</a>'
    assert shortcodes.parse_crosslinks(html, _resolver) == '<a href="https://example.test/wordpress/123">articolo</a>'


def test_cta_to_a_post_without_url_flag():
    template = '<a href="[url]">[text]</a>'
    out = shortcodes.parse_cta('[cta WordPress id="123" text="clicca qui"]', template, _resolver)
    assert out == '<a href="https://example.test/wordpress/123">clicca qui</a>'


def test_cta_with_an_address_is_unchanged():
    template = '<a href="[url]">[text]</a>'
    out = shortcodes.parse_cta('[cta url="https://shop.test" text="Compra"]', template, _resolver)
    assert out == '<a href="https://shop.test">Compra</a>'


def test_parse_images_replaces_shortcode_with_the_nth_post_image():
    out = shortcodes.parse_images("<td>[image 2]</td>", ["https://x.test/a.jpg", "https://x.test/b.jpg"])
    assert out.startswith('<td><img src="https://x.test/b.jpg"')
    assert "max-width:100%" in out


def test_parse_images_removes_out_of_range_shortcodes():
    assert shortcodes.parse_images("a[image 3]b[image 0]c", ["https://x.test/a.jpg"]) == "abc"


def test_parse_images_is_case_insensitive():
    assert 'src="https://x.test/a.jpg"' in shortcodes.parse_images("[IMAGE 1]", ["https://x.test/a.jpg"])
