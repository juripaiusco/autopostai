"""End-to-end DRY_RUN di newsletter_send contro il DB di sviluppo.

Stesso principio di test_posts_send_dryrun.py: tutto dentro una transazione
rollback-ata, richiede PUBLISHER_DRY_RUN=1 e un DB raggiungibile.
"""

import json
import uuid

import pytest
from sqlalchemy import text

from publisher import config
from publisher.db.engine import get_engine
from publisher.tasks import newsletter_send

NEWSLETTER_CHANNELS = json.dumps({"newsletter": {"on": True, "provider": "smtp_custom"}})


def _make_account(conn) -> int:
    email = f"nl-{uuid.uuid4()}@example.com"
    user_id = conn.execute(
        text(
            """
            INSERT INTO users (name, email, password, channels, created_at, updated_at)
            VALUES ('NL Test', :email, 'x', :channels, NOW(), NOW())
            """
        ),
        {"email": email, "channels": json.dumps({"newsletter": {"on": True}})},
    ).lastrowid

    conn.execute(
        text(
            """
            INSERT INTO settings (user_id, nl_smtp_host, nl_smtp_port, nl_smtp_username,
                                   nl_smtp_password, nl_smtp_encryption, nl_smtp_sender, nl_template)
            VALUES (:uid, 'smtp.test.it', '587', 'news@example.com', 'secret', 'tls',
                    'Trattoria <news@example.com>', '<html>[content]</html>')
            """
        ),
        {"uid": user_id},
    )
    return user_id


def _make_contact(conn, user_id: int, status: str = "active") -> int:
    return conn.execute(
        text(
            """
            INSERT INTO contacts (user_id, email, status, created_at, updated_at)
            VALUES (:uid, :email, :status, NOW(), NOW())
            """
        ),
        {"uid": user_id, "email": f"{uuid.uuid4()}@example.com", "status": status},
    ).lastrowid


def _make_post(conn, user_id: int, channels: str = NEWSLETTER_CHANNELS) -> int:
    return conn.execute(
        text(
            """
            INSERT INTO posts
                (user_id, created_by_user_id, title, ai_content, channels,
                 preview, published, published_at, created_at, updated_at)
            VALUES
                (:uid, :uid, 'Newsletter test', 'contenuto di prova', :channels,
                 0, 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())
            """
        ),
        {"uid": user_id, "channels": channels},
    ).lastrowid


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_newsletter_send_dispatches_batch_and_publishes_on_first_send():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = _make_account(conn)
        contact_id = _make_contact(conn, user_id)
        post_id = _make_post(conn, user_id)

        newsletter_send.run(conn)

        send = conn.execute(
            text("SELECT status FROM email_sends WHERE post_id = :pid AND contact_id = :cid"),
            {"pid": post_id, "cid": contact_id},
        ).mappings().first()
        assert send is not None
        assert send["status"] == "sent"

        row = conn.execute(
            text("SELECT channels, published FROM posts WHERE id = :id"), {"id": post_id}
        ).mappings().first()
        saved = json.loads(row["channels"])
        assert saved["newsletter"]["id"] == f"smtp-{post_id}"
        assert str(row["published"]) == "1"
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_newsletter_send_excludes_unsubscribed_and_suppressed_contacts():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = _make_account(conn)
        active = _make_contact(conn, user_id, "active")
        _make_contact(conn, user_id, "unsubscribed")
        suppressed_email_contact = _make_contact(conn, user_id, "active")
        suppressed_email = conn.execute(
            text("SELECT email FROM contacts WHERE id = :id"), {"id": suppressed_email_contact}
        ).scalar()
        conn.execute(
            text(
                "INSERT INTO suppression_list (user_id, email, reason, created_at) "
                "VALUES (:uid, :email, 'hard_bounce', NOW())"
            ),
            {"uid": user_id, "email": suppressed_email},
        )
        post_id = _make_post(conn, user_id)

        newsletter_send.run(conn)

        sent_contact_ids = conn.execute(
            text("SELECT contact_id FROM email_sends WHERE post_id = :pid"), {"pid": post_id}
        ).scalars().all()
        assert sent_contact_ids == [active]
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_newsletter_send_throttles_to_batch_size_per_tick():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = _make_account(conn)
        contact_ids = [_make_contact(conn, user_id) for _ in range(config.NEWSLETTER_SMTP_BATCH_SIZE + 3)]
        post_id = _make_post(conn, user_id)

        newsletter_send.run(conn)

        sent_count = conn.execute(
            text("SELECT COUNT(*) FROM email_sends WHERE post_id = :pid"), {"pid": post_id}
        ).scalar()
        assert sent_count == config.NEWSLETTER_SMTP_BATCH_SIZE

        # Secondo giro: prosegue sui contatti rimasti (post gia' pubblicato,
        # ma non ancora esaurito) invece di fermarsi perche' published=1.
        newsletter_send.run(conn)
        sent_count_after = conn.execute(
            text("SELECT COUNT(*) FROM email_sends WHERE post_id = :pid"), {"pid": post_id}
        ).scalar()
        assert sent_count_after == len(contact_ids)
    finally:
        trans.rollback()
        conn.close()


@pytest.mark.skipif(not config.DRY_RUN, reason="richiede PUBLISHER_DRY_RUN=1")
def test_newsletter_send_ignores_mailchimp_and_brevo_providers():
    engine = get_engine()
    conn = engine.connect()
    trans = conn.begin()
    try:
        user_id = _make_account(conn)
        _make_contact(conn, user_id)
        channels = json.dumps({"newsletter": {"on": True, "provider": "mailchimp", "list": {"provider": "mailchimp", "id": "1"}}})
        post_id = _make_post(conn, user_id, channels=channels)

        newsletter_send.run(conn)

        count = conn.execute(
            text("SELECT COUNT(*) FROM email_sends WHERE post_id = :pid"), {"pid": post_id}
        ).scalar()
        assert count == 0
    finally:
        trans.rollback()
        conn.close()
