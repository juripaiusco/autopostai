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


def test_set_result_writes_back_into_raw():
    channels = Channels.parse(_sample())
    channels.entry("facebook").set_result("fb1", "http://fb", None)
    assert channels.to_dict()["facebook"]["id"] == "fb1"
    assert channels.to_dict()["facebook"]["url"] == "http://fb"
