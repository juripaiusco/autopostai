"""Unit test del parsing tipizzato del JSON channels (shape v2)."""

import json

from publisher.domain.channels import Channels


def _sample():
    return {
        "facebook": {"on": True, "comments_enabled": True, "auto_reply_enabled": False},
        "instagram": {"on": True, "comments_enabled": False, "auto_reply_enabled": False, "id": "ig1", "url": "u"},
        "linkedin": {"on": False},
        "wordpress": {"on": True, "categories": [{"id": "12", "name": "News", "on": True}, {"id": "9", "on": False}]},
        "newsletter": {"on": True, "list": {"provider": "brevo", "id": "5", "name": "Main"}},
    }


def test_parse_accepts_dict_and_json_string():
    assert Channels.parse(_sample()).entry("facebook").is_on
    assert Channels.parse(json.dumps(_sample())).entry("facebook").is_on


def test_publishable_skips_off_and_already_published():
    keys = [e.key for e in Channels.parse(_sample()).publishable()]
    # instagram ha gia' un id -> escluso; linkedin off -> escluso.
    assert keys == ["facebook", "wordpress", "newsletter"]


def test_all_on_published_false_until_every_on_channel_has_id():
    channels = Channels.parse(_sample())
    assert channels.all_on_published() is False

    for entry in channels.publishable():
        entry.set_result(f"id-{entry.key}", f"url-{entry.key}")
    assert channels.all_on_published() is True


def test_wordpress_category_ids_only_selected():
    entry = Channels.parse(_sample()).entry("wordpress")
    assert entry.wordpress_category_ids() == ["12"]


def test_newsletter_provider_and_list():
    entry = Channels.parse(_sample()).entry("newsletter")
    assert entry.newsletter_provider() == "brevo"
    assert entry.newsletter_list_id() == "5"


def test_newsletter_provider_prefers_top_level_over_list():
    # v2: PostController::buildChannelsPayload scrive 'provider' in cima al
    # canale; il fallback su list.provider resta solo per righe pre-esistenti.
    entry = Channels.parse({"newsletter": {"on": True, "provider": "smtp_custom"}}).entry("newsletter")
    assert entry.newsletter_provider() == "smtp_custom"


def test_smtp_custom_newsletter_never_needs_publish():
    # Non passa dal ciclo generico publish() (una chiamata = un id): se ne
    # occupa interamente publisher/tasks/newsletter_send.py, a batch.
    entry = Channels.parse({"newsletter": {"on": True, "provider": "smtp_custom"}}).entry("newsletter")
    assert entry.needs_publish is False
    assert entry.is_on is True


def test_smtp_custom_newsletter_excluded_from_publishable():
    channels = Channels.parse({
        "facebook": {"on": True},
        "newsletter": {"on": True, "provider": "smtp_custom"},
    })
    assert [e.key for e in channels.publishable()] == ["facebook"]


def test_all_on_published_still_false_for_smtp_custom_without_id():
    # all_on_published() guarda already_published (id), non needs_publish:
    # un canale smtp_custom non ancora processato blocca comunque il post.
    channels = Channels.parse({"newsletter": {"on": True, "provider": "smtp_custom"}})
    assert channels.all_on_published() is False

    channels.entry("newsletter").set_result("smtp-1", None)
    assert channels.all_on_published() is True


def test_set_result_writes_back_into_raw():
    channels = Channels.parse(_sample())
    channels.entry("facebook").set_result("fb1", "http://fb", None)
    assert channels.to_dict()["facebook"]["id"] == "fb1"
    assert channels.to_dict()["facebook"]["url"] == "http://fb"


def _social(reply_n=None, on=True, comments_enabled=True):
    return Channels.parse({"facebook": {"on": on, "comments_enabled": comments_enabled, "reply_n": reply_n}}).entry(
        "facebook"
    )


def test_needs_comment_fetch_true_below_cap():
    assert _social(reply_n=5).needs_comment_fetch(3) is True


def test_needs_comment_fetch_false_at_or_above_cap():
    assert _social(reply_n=5).needs_comment_fetch(5) is False


def test_needs_comment_fetch_false_without_cap_configured():
    assert _social(reply_n=None).needs_comment_fetch(0) is False


def test_needs_comment_fetch_false_when_comments_disabled():
    assert _social(reply_n=5, comments_enabled=False).needs_comment_fetch(0) is False


def test_social_monitoring_complete_off_channel():
    assert _social(on=False).social_monitoring_complete(0) is True


def test_social_monitoring_complete_no_cap():
    assert _social(reply_n=None).social_monitoring_complete(0) is True


def test_social_monitoring_complete_under_cap_is_not_complete():
    assert _social(reply_n=5).social_monitoring_complete(2) is False


def test_social_monitoring_complete_cap_reached():
    assert _social(reply_n=5).social_monitoring_complete(5) is True
