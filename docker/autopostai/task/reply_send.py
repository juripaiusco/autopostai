import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.ai_generate import ai_generate
from task.reply.base import BaseReply
from task.reply.facebook import FacebookReply
from task.reply.instagram import InstagramReply
from task.reply.linkedin import LinkedInReply
from services.meta import Meta
from services.linkedin import LinkedIn


def reply_send(debug = False):

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Reply send - START -----------------")

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Database query")

    mysql = Mysql()
    mysql.connect()

    rows = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}comments.id AS id,
                    {cfg.DB_PREFIX}comments.post_id AS post_id,
                    {cfg.DB_PREFIX}users.id AS user_id,
                    {cfg.DB_PREFIX}comments.channel AS channel,
                    {cfg.DB_PREFIX}comments.from_id AS from_id,
                    {cfg.DB_PREFIX}comments.from_name AS from_name,
                    {cfg.DB_PREFIX}comments.message_id AS message_id,
                    {cfg.DB_PREFIX}comments.message AS message,
                    {cfg.DB_PREFIX}posts.id AS post_id,
                    {cfg.DB_PREFIX}posts.ai_prompt_post AS ai_prompt_post,
                    {cfg.DB_PREFIX}posts.ai_content AS ai_content,
                    {cfg.DB_PREFIX}posts.ai_prompt_comment AS ai_prompt_comment,
                    {cfg.DB_PREFIX}posts.img AS img,
                    {cfg.DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                    {cfg.DB_PREFIX}settings.ai_personality AS ai_personality,
                    {cfg.DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                    {cfg.DB_PREFIX}settings.ai_comment_prefix AS ai_comment_prefix,
                    {cfg.DB_PREFIX}settings.openai_api_key AS openai_api_key,
                    {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id,
                    {cfg.DB_PREFIX}settings.linkedin_client_id AS linkedin_client_id,
                    {cfg.DB_PREFIX}settings.linkedin_client_secret AS linkedin_client_secret,
                    {cfg.DB_PREFIX}settings.linkedin_token AS linkedin_token

                FROM {cfg.DB_PREFIX}comments
                    INNER JOIN {cfg.DB_PREFIX}posts ON {cfg.DB_PREFIX}posts.id = {cfg.DB_PREFIX}comments.post_id
                    INNER JOIN {cfg.DB_PREFIX}users ON {cfg.DB_PREFIX}users.id = {cfg.DB_PREFIX}posts.user_id
                    INNER JOIN {cfg.DB_PREFIX}settings ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}users.id

            WHERE {cfg.DB_PREFIX}comments.reply IS NULL
                AND {cfg.DB_PREFIX}posts.deleted_at is null
                LIMIT 0, 1
        """)

    for row in rows:

        if debug is True:
            print(
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                "Reply to Comments - Post ID:",
                row['post_id']
            )

        base_reply = BaseReply(data=row)

        # Preparo i prompt della risposta in base al tipo di canale
        if row['channel'] == 'facebook':
            prompt = FacebookReply(data=row).prompt_get()

        if row['channel'] == 'instagram':
            prompt = InstagramReply(data=row).prompt_get()

        if row['channel'] == 'linkedin':
            prompt = LinkedInReply(data=row).prompt_get()

        # Recupero la risposta dal LLM
        reply = ai_generate(
            data=row,
            prompt=prompt,
            img_path=base_reply.img_path_get() if row['img_ai_check_on'] == '1' else None,
            type="reply",
            debug=debug
        )
        reply_id = None

        # Invio la risposta ai commenti Meta ----------------------------------- |
        if reply is not None and row['meta_page_id'] is not None:
            meta = Meta(page_id=row['meta_page_id'])

            if row['channel'] == "facebook":
                reply_id = meta.fb_reply_comments(row['message_id'], reply)
                if debug is True:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Facebook - reply ID:",
                          reply_id)

            if row['channel'] == "instagram":
                reply_id = meta.ig_reply_comments(row['message_id'], reply)
                if debug is True:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Instagram - reply ID:",
                          reply_id)

        # Invio risposta ai commenti LinkedIn --------------------------------- |
        if reply is not None and row['linkedin_client_id'] is not None:
            if row['channel'] == "linkedin":
                linkedin = LinkedIn(
                    client_id=row['linkedin_client_id'],
                    client_secret=row['linkedin_client_secret'],
                    token=row['linkedin_token']
                )
                reply_id, reply = linkedin.reply_comments(
                    comment_urn=row['message_id'],
                    actor_urn=row['from_id'],
                    actor_name=row['from_name'],
                    reply_message=reply
                )
                if debug is True:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "LinkedIn - reply ID:",
                          reply_id)

        #########################################################
        #                                                       #
        #           Imposto il commento come replicato          #
        #                                                       #
        #########################################################

        if reply_id is not None:
            mysql.query(
                query=f"UPDATE {cfg.DB_PREFIX}comments SET reply_id = %s, reply = %s, reply_created_time = %s WHERE id = %s",
                parameters=(reply_id, reply, cfg.CURRENT_TIME, row['id'])
            )

    mysql.close()

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Reply send - END -------------------")
