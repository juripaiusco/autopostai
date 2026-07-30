"""Repository: tutte le query centralizzate e PARAMETRIZZATE.

In v1 la stessa grande SELECT posts+settings era copiaincollata in 6 file e i
valori venivano interpolati con f-string (superficie di SQL-injection). Qui la
query vive in un posto solo e i valori passano come bind param `:nome`.
"""

import json
from decimal import Decimal

from sqlalchemy import text
from sqlalchemy.engine import Connection

from publisher import config


class PostRepository:
    def __init__(self, conn: Connection):
        self.conn = conn

    def due_posts(self, now: str) -> list[dict]:
        """Post schedulati e ancora da pubblicare, con le credenziali dell'account.

        Filtro identico a v1: non pubblicato, non anteprima, orario di
        pubblicazione raggiunto, non soft-deleted.
        """
        posts = config.table("posts")
        settings = config.table("settings")

        rows = self.conn.execute(
            text(
                f"""
                SELECT  p.id                     AS id,
                        p.user_id                AS user_id,
                        p.created_by_user_id     AS created_by_user_id,
                        p.title                  AS title,
                        p.ai_prompt_post         AS ai_prompt_post,
                        p.ai_content             AS ai_content,
                        p.img                    AS img,
                        p.img_ai_check_on        AS img_ai_check_on,
                        p.channels               AS channels,
                        s.ai_personality         AS ai_personality,
                        s.ai_prompt_prefix       AS ai_prompt_prefix,
                        s.openai_api_key         AS openai_api_key,
                        s.meta_page_id           AS meta_page_id,
                        s.linkedin_person_id     AS linkedin_person_id,
                        s.linkedin_company_id    AS linkedin_company_id,
                        s.linkedin_client_id     AS linkedin_client_id,
                        s.linkedin_client_secret AS linkedin_client_secret,
                        s.linkedin_token         AS linkedin_token,
                        s.wordpress_url          AS wordpress_url,
                        s.wordpress_username     AS wordpress_username,
                        s.wordpress_password     AS wordpress_password,
                        s.wordpress_cat_id       AS wordpress_cat_id,
                        s.nl_mailchimp_api        AS nl_mailchimp_api,
                        s.nl_mailchimp_datacenter AS nl_mailchimp_datacenter,
                        s.nl_mailchimp_list_id    AS nl_mailchimp_list_id,
                        s.nl_mailchimp_from_name  AS nl_mailchimp_from_name,
                        s.nl_mailchimp_from_email AS nl_mailchimp_from_email,
                        s.nl_brevo_api            AS nl_brevo_api,
                        s.nl_brevo_list_id        AS nl_brevo_list_id,
                        s.nl_brevo_from_name      AS nl_brevo_from_name,
                        s.nl_brevo_from_email     AS nl_brevo_from_email,
                        s.nl_template             AS nl_template,
                        s.nl_template_cta         AS nl_template_cta
                    FROM {posts} p
                    INNER JOIN {settings} s ON s.user_id = p.user_id
                WHERE p.published = 0
                    AND p.preview = 0
                    AND p.published_at <= :now
                    AND p.deleted_at IS NULL
                """
            ),
            {"now": now},
        ).mappings().all()

        return [dict(r) for r in rows]

    def due_smtp_custom_posts(self, now: str) -> list[dict]:
        """Post con newsletter smtp_custom accesa, dovuti alla pubblicazione.

        A differenza di due_posts(): niente filtro su `published` — un invio
        smtp_custom si spalma su piu' run (batch da 4 contatti a tick,
        publisher/tasks/newsletter_send.py), quindi va ripescato anche dopo
        che il post e' gia' stato marcato pubblicato (primo batch inviato).
        Il task si ferma da solo quando non restano piu' contatti da servire.
        """
        posts = config.table("posts")
        settings = config.table("settings")

        rows = self.conn.execute(
            text(
                f"""
                SELECT  p.id                  AS id,
                        p.user_id             AS user_id,
                        p.title                AS title,
                        p.ai_prompt_post       AS ai_prompt_post,
                        p.ai_content           AS ai_content,
                        p.img                  AS img,
                        p.img_ai_check_on      AS img_ai_check_on,
                        p.channels             AS channels,
                        s.ai_personality       AS ai_personality,
                        s.ai_prompt_prefix     AS ai_prompt_prefix,
                        s.openai_api_key       AS openai_api_key,
                        s.nl_template          AS nl_template,
                        s.nl_template_cta      AS nl_template_cta,
                        s.nl_smtp_host         AS nl_smtp_host,
                        s.nl_smtp_port         AS nl_smtp_port,
                        s.nl_smtp_username     AS nl_smtp_username,
                        s.nl_smtp_password     AS nl_smtp_password,
                        s.nl_smtp_encryption   AS nl_smtp_encryption,
                        s.nl_smtp_sender       AS nl_smtp_sender
                    FROM {posts} p
                    INNER JOIN {settings} s ON s.user_id = p.user_id
                WHERE p.preview = 0
                    AND p.published_at <= :now
                    AND p.deleted_at IS NULL
                    AND JSON_EXTRACT(p.channels, '$.newsletter.on') = true
                    AND JSON_UNQUOTE(JSON_EXTRACT(p.channels, '$.newsletter.provider')) = 'smtp_custom'
                """
            ),
            {"now": now},
        ).mappings().all()

        return [dict(r) for r in rows]

    def channels_of(self, post_id: int) -> dict | None:
        """Solo il JSON channels di un post (per la risoluzione degli shortcode url)."""
        posts = config.table("posts")
        row = self.conn.execute(
            text(f"SELECT user_id, channels FROM {posts} WHERE id = :id"),
            {"id": post_id},
        ).mappings().first()
        return dict(row) if row else None

    def save_channels(self, post_id: int, channels: dict) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET channels = :channels WHERE id = :id"),
            {"channels": json.dumps(channels), "id": post_id},
        )

    def save_ai_content(self, post_id: int, content: str) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET ai_content = :content WHERE id = :id"),
            {"content": content, "id": post_id},
        )

    def mark_published(self, post_id: int) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET published = 1 WHERE id = :id"),
            {"id": post_id},
        )

    def abandon_over_limit(self, post_id: int) -> None:
        """Post abbandonato perche' l'account ha esaurito i token: marcato
        published=1 + task_complete=1 cosi' non viene piu' processato (fedele a v1,
        dove ai_generate faceva lo stesso quando il limite era superato)."""
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET published = 1, task_complete = 1 WHERE id = :id"),
            {"id": post_id},
        )

    def due_for_comment_monitoring(self, now: str) -> dict | None:
        """Un post pubblicato, non ancora task_complete, fuori dal backoff
        (on_hold_until), con il conteggio commenti per canale social — un post per
        run, come v1. Usato sia da comments_get che da task_complete."""
        posts = config.table("posts")
        settings = config.table("settings")
        comments = config.table("comments")

        row = self.conn.execute(
            text(
                f"""
                SELECT  p.id                     AS id,
                        p.user_id                AS user_id,
                        p.created_by_user_id     AS created_by_user_id,
                        p.ai_prompt_post         AS ai_prompt_post,
                        p.img                    AS img,
                        p.img_ai_check_on        AS img_ai_check_on,
                        p.channels               AS channels,
                        p.check_attempts         AS check_attempts,
                        p.on_hold_until          AS on_hold_until,
                        p.published_at           AS published_at,
                        s.ai_personality         AS ai_personality,
                        s.ai_prompt_prefix       AS ai_prompt_prefix,
                        s.ai_comment_prefix      AS ai_comment_prefix,
                        s.openai_api_key         AS openai_api_key,
                        s.meta_page_id           AS meta_page_id,
                        s.linkedin_company_id    AS linkedin_company_id,
                        s.linkedin_token         AS linkedin_token,
                        s.nl_brevo_api           AS nl_brevo_api,
                        SUM(CASE WHEN c.channel = 'facebook' THEN 1 ELSE 0 END)  AS facebook_comments_count,
                        SUM(CASE WHEN c.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count,
                        SUM(CASE WHEN c.channel = 'linkedin' THEN 1 ELSE 0 END)  AS linkedin_comments_count
                    FROM {posts} p
                    INNER JOIN {settings} s ON s.user_id = p.user_id
                    LEFT JOIN {comments} c ON c.post_id = p.id
                WHERE p.published = 1
                    AND p.task_complete = 0
                    AND (p.on_hold_until IS NULL OR p.on_hold_until <= :now)
                    AND p.deleted_at IS NULL
                GROUP BY p.id
                LIMIT 1
                """
            ),
            {"now": now},
        ).mappings().first()

        return dict(row) if row else None

    def due_for_update(self) -> dict | None:
        """Un post con updated=2 (modificato, in attesa di risync), un post per run."""
        posts = config.table("posts")
        settings = config.table("settings")

        row = self.conn.execute(
            text(
                f"""
                SELECT  p.id                 AS id,
                        p.user_id            AS user_id,
                        p.ai_content         AS ai_content,
                        p.channels           AS channels,
                        s.wordpress_url      AS wordpress_url,
                        s.wordpress_username AS wordpress_username,
                        s.wordpress_password AS wordpress_password
                    FROM {posts} p
                    INNER JOIN {settings} s ON s.user_id = p.user_id
                WHERE p.updated = '2'
                    AND p.deleted_at IS NULL
                LIMIT 1
                """
            )
        ).mappings().first()

        return dict(row) if row else None

    def set_updated(self, post_id: int, value: str) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET updated = :value WHERE id = :id"),
            {"value": value, "id": post_id},
        )

    def due_for_deletion(self, now: str) -> list[dict]:
        """Post pubblicati, soft-deleted (deleted_at passato), non ancora rimossi
        dai canali remoti (deleted=0)."""
        posts = config.table("posts")
        settings = config.table("settings")

        rows = self.conn.execute(
            text(
                f"""
                SELECT  p.id                      AS id,
                        p.user_id                 AS user_id,
                        p.channels                AS channels,
                        s.meta_page_id             AS meta_page_id,
                        s.linkedin_company_id      AS linkedin_company_id,
                        s.linkedin_token           AS linkedin_token,
                        s.wordpress_url            AS wordpress_url,
                        s.wordpress_username       AS wordpress_username,
                        s.wordpress_password       AS wordpress_password,
                        s.nl_mailchimp_api         AS nl_mailchimp_api,
                        s.nl_mailchimp_datacenter  AS nl_mailchimp_datacenter,
                        s.nl_brevo_api             AS nl_brevo_api
                    FROM {posts} p
                    INNER JOIN {settings} s ON s.user_id = p.user_id
                WHERE p.published = 1
                    AND p.deleted = 0
                    AND p.deleted_at IS NOT NULL
                    AND p.deleted_at <= :now
                """
            ),
            {"now": now},
        ).mappings().all()

        return [dict(r) for r in rows]

    def set_deleted(self, post_id: int, value: str) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET deleted = :value WHERE id = :id"),
            {"value": value, "id": post_id},
        )

    def set_task_complete(self, post_id: int) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(f"UPDATE {posts} SET task_complete = 1 WHERE id = :id"),
            {"id": post_id},
        )

    def set_hold(self, post_id: int, on_hold_until: str) -> None:
        posts = config.table("posts")
        self.conn.execute(
            text(
                f"""
                UPDATE {posts}
                SET on_hold_until = :hold, check_attempts = check_attempts + 1
                WHERE id = :id
                """
            ),
            {"hold": on_hold_until, "id": post_id},
        )


class TokenLogRepository:
    """Traccia i token LLM consumati (tabella token_logs, come v1)."""

    def __init__(self, conn: Connection):
        self.conn = conn

    def usage_this_month(self, user_id: int) -> dict | None:
        """Token limite mensile dell'utente e quanti ne ha gia' usati (dal 1 del mese)."""
        users = config.table("users")
        token_logs = config.table("token_logs")

        row = self.conn.execute(
            text(
                f"""
                SELECT  u.id AS id,
                        u.tokens_limit AS tokens_limit,
                        COALESCE(SUM(
                            CASE
                                WHEN tl.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                                 AND tl.created_at < DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')
                                THEN tl.tokens_used ELSE 0
                            END
                        ), 0) AS tokens_used_total
                    FROM {users} u
                    LEFT JOIN {token_logs} tl ON tl.user_id = u.id
                WHERE u.id = :user_id
                GROUP BY u.id
                """
            ),
            {"user_id": user_id},
        ).mappings().first()

        return dict(row) if row else None

    def is_over_limit(self, user_id: int) -> bool:
        """True se l'account ha esaurito i token del mese. Fedele a v1:
        used >= limit, e tokens_limit = 0 (o NULL) significa BLOCCATO (0 >= 0)."""
        usage = self.usage_this_month(user_id)
        if not usage:
            return False
        return Decimal(usage["tokens_used_total"] or 0) >= Decimal(usage["tokens_limit"] or 0)

    def log(self, user_id: int, ref_type: str, reference_id: int, tokens_used: int, now: str) -> None:
        token_logs = config.table("token_logs")
        self.conn.execute(
            text(
                f"""
                INSERT INTO {token_logs} (user_id, type, reference_id, tokens_used, created_at, updated_at)
                VALUES (:user_id, :type, :reference_id, :tokens, :now, :now)
                """
            ),
            {
                "user_id": user_id,
                "type": ref_type,
                "reference_id": reference_id,
                "tokens": tokens_used,
                "now": now,
            },
        )


class CommentRepository:
    """Commenti fetchati dai canali social e le relative risposte automatiche."""

    def __init__(self, conn: Connection):
        self.conn = conn

    def exists(self, post_id: int, channel: str, message_id: str) -> bool:
        comments = config.table("comments")
        row = self.conn.execute(
            text(
                f"SELECT 1 FROM {comments} WHERE post_id = :post_id AND channel = :channel AND message_id = :mid LIMIT 1"
            ),
            {"post_id": post_id, "channel": channel, "mid": message_id},
        ).first()
        return row is not None

    def save(
        self,
        post_id: int,
        channel: str,
        from_id: str | None,
        from_name: str | None,
        message_id: str,
        message: str,
        message_created_time: str,
        now: str,
    ) -> None:
        # Dedup a livello applicativo (exists()) invece di un vincolo UNIQUE nello
        # schema: coerente con lo schema v2 attuale (nessun indice unico su
        # post_id+channel+message_id), come faceva l'INSERT IGNORE di v1.
        if self.exists(post_id, channel, message_id):
            return

        comments = config.table("comments")
        self.conn.execute(
            text(
                f"""
                INSERT INTO {comments}
                    (post_id, channel, from_id, from_name, message_id, message, message_created_time, created_at, updated_at)
                VALUES
                    (:post_id, :channel, :from_id, :from_name, :mid, :message, :created_time, :now, :now)
                """
            ),
            {
                "post_id": post_id,
                "channel": channel,
                "from_id": from_id,
                "from_name": from_name,
                "mid": message_id,
                "message": message,
                "created_time": message_created_time,
                "now": now,
            },
        )

    def due_for_reply(self, limit: int = 20) -> list[dict]:
        """Commenti senza risposta, coi dati del post per decidere se rispondere.

        A differenza di v1 (LIMIT 1 secco, un solo toggle reply_on) qui si legge un
        piccolo batch: il canale del primo commento potrebbe avere auto_reply_enabled
        spento (si traccia ma non si risponde), e con LIMIT 1 quel commento
        bloccherebbe la coda per sempre. Il task elabora il primo commento
        idoneo nel batch e si ferma li' (un reply per run, come v1).
        """
        comments = config.table("comments")
        posts = config.table("posts")
        users = config.table("users")
        settings = config.table("settings")

        rows = self.conn.execute(
            text(
                f"""
                SELECT  c.id                     AS id,
                        c.post_id                AS post_id,
                        c.channel                AS channel,
                        c.from_id                AS from_id,
                        c.from_name               AS from_name,
                        c.message_id              AS message_id,
                        c.message                 AS message,
                        p.user_id                AS user_id,
                        p.ai_prompt_post          AS ai_prompt_post,
                        p.ai_content              AS ai_content,
                        p.ai_prompt_comment       AS ai_prompt_comment,
                        p.img                     AS img,
                        p.img_ai_check_on         AS img_ai_check_on,
                        p.channels                AS channels,
                        s.ai_personality          AS ai_personality,
                        s.ai_prompt_prefix        AS ai_prompt_prefix,
                        s.ai_comment_prefix       AS ai_comment_prefix,
                        s.openai_api_key          AS openai_api_key,
                        s.meta_page_id            AS meta_page_id,
                        s.linkedin_company_id     AS linkedin_company_id,
                        s.linkedin_token          AS linkedin_token
                    FROM {comments} c
                    INNER JOIN {posts} p ON p.id = c.post_id
                    INNER JOIN {users} u ON u.id = p.user_id
                    INNER JOIN {settings} s ON s.user_id = u.id
                WHERE c.reply IS NULL
                    AND p.deleted_at IS NULL
                ORDER BY c.id
                LIMIT :limit
                """
            ),
            {"limit": limit},
        ).mappings().all()

        return [dict(r) for r in rows]

    def mark_replied(self, comment_id: int, reply_id: str, reply_text: str, now: str) -> None:
        comments = config.table("comments")
        self.conn.execute(
            text(
                f"""
                UPDATE {comments}
                SET reply_id = :reply_id, reply = :reply, reply_created_time = :now
                WHERE id = :id
                """
            ),
            {"reply_id": reply_id, "reply": reply_text, "now": now, "id": comment_id},
        )


class PushNotificationRepository:
    """Accodamento notifiche pending (pattern pending-row Laravel).

    Non firmiamo/inviamo WebPush qui: inseriamo solo una riga con sent_at NULL;
    la firma VAPID e l'invio li fa il comando Laravel `notifications:send-pending`.
    kind='post_published' fa scegliere al comando la classe PostPublishedAlert
    (solo WebPush, NON in campanella).
    """

    def __init__(self, conn: Connection):
        self.conn = conn

    def user_has_subscription(self, user_id: int) -> bool:
        subs = config.table("push_subscriptions")
        row = self.conn.execute(
            text(
                f"""
                SELECT 1 FROM {subs}
                WHERE subscribable_type = :type AND subscribable_id = :uid
                LIMIT 1
                """
            ),
            {"type": "App\\Models\\User", "uid": user_id},
        ).first()
        return row is not None

    def enqueue_post_published(self, user_id: int, title: str, url: str, now: str) -> None:
        push = config.table("push_notifications")
        self.conn.execute(
            text(
                f"""
                INSERT INTO {push}
                    (created_by_user_id, user_id, kind, title, body, url, created_at, updated_at)
                VALUES (:uid, :uid, 'post_published', :title, :body, :url, :now, :now)
                """
            ),
            {"uid": user_id, "title": title, "body": "Post inviato", "url": url, "now": now},
        )


class ContactRepository:
    """Contatti newsletter (smtp_custom) e il relativo log invii (email_sends)."""

    def __init__(self, conn: Connection):
        self.conn = conn

    def sendable_for_post(self, user_id: int, post_id: int, limit: int) -> list[dict]:
        """Contatti attivi, non in suppression list, non ancora processati per
        QUESTO post (nessuna riga email_sends esistente) — i piu' vecchi prima,
        cosi' un batch limitato avanza sempre sui prossimi al giro successivo."""
        contacts = config.table("contacts")
        suppression = config.table("suppression_list")
        email_sends = config.table("email_sends")

        rows = self.conn.execute(
            text(
                f"""
                SELECT c.id AS id, c.email AS email
                    FROM {contacts} c
                WHERE c.user_id = :user_id
                    AND c.status = 'active'
                    AND c.deleted_at IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM {suppression} sl
                        WHERE sl.user_id = c.user_id AND sl.email = c.email
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM {email_sends} es
                        WHERE es.contact_id = c.id AND es.post_id = :post_id
                    )
                ORDER BY c.id
                LIMIT :limit
                """
            ),
            {"user_id": user_id, "post_id": post_id, "limit": limit},
        ).mappings().all()

        return [dict(r) for r in rows]

    def create_placeholder(self, contact_id: int, post_id: int, now: str) -> int:
        """Riserva la riga (status='queued') PRIMA di inviare: serve l'id per
        comporre il pixel di tracking apertura (Step 8) dentro l'html, quindi
        l'id deve esistere prima che l'invio parta, non dopo."""
        email_sends = config.table("email_sends")
        return self.conn.execute(
            text(
                f"""
                INSERT INTO {email_sends} (contact_id, post_id, status, created_at, updated_at)
                VALUES (:contact_id, :post_id, 'queued', :now, :now)
                """
            ),
            {"contact_id": contact_id, "post_id": post_id, "now": now},
        ).lastrowid

    def mark_sent(self, send_id: int, now: str) -> None:
        email_sends = config.table("email_sends")
        self.conn.execute(
            text(f"UPDATE {email_sends} SET status = 'sent', sent_at = :now WHERE id = :id"),
            {"now": now, "id": send_id},
        )

    def mark_send_bounced(self, send_id: int, contact_id: int, now: str, error_message: str) -> None:
        email_sends = config.table("email_sends")
        self.conn.execute(
            text(
                f"""
                UPDATE {email_sends}
                SET status = 'bounced', bounced_at = :now, error_message = :error_message
                WHERE id = :id
                """
            ),
            {"now": now, "id": send_id, "error_message": error_message},
        )
        self.mark_bounced(contact_id)

    def mark_bounced(self, contact_id: int) -> None:
        contacts = config.table("contacts")
        self.conn.execute(
            text(f"UPDATE {contacts} SET status = 'bounced' WHERE id = :id"),
            {"id": contact_id},
        )

    def stats_for_post(self, post_id: int) -> dict:
        """Conteggio per stato degli invii di un post — scritto in
        channels.newsletter.stats da newsletter_send.py (Step 8). Bucket per
        stato (non cumulativo): un invio compare in una sola voce alla volta."""
        email_sends = config.table("email_sends")
        rows = self.conn.execute(
            text(f"SELECT status, COUNT(*) AS n FROM {email_sends} WHERE post_id = :post_id GROUP BY status"),
            {"post_id": post_id},
        ).mappings().all()
        counts = {r["status"]: r["n"] for r in rows}
        return {s: int(counts.get(s, 0)) for s in ("queued", "sent", "delivered", "opened", "clicked", "bounced")}
