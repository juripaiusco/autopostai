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
