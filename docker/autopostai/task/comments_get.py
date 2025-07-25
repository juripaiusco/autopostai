import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.comments.facebook import FacebookComment
from task.comments.instagram import InstagramComment
from task.comments.linkedin import LinkedInComment
from decimal import Decimal

def comments_get(debug = False):

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Comments get - START -----------------")

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Database query")

    mysql = Mysql()
    mysql.connect()

    #########################################################
    #                                                       #
    #                     Query MySQL                       #
    #                                                       #
    #########################################################

    time_now = datetime.now(cfg.LOCAL_TIMEZONE)
    rows = mysql.query(f"""
        SELECT  {cfg.DB_PREFIX}posts.id AS id,
                {cfg.DB_PREFIX}posts.user_id AS user_id,
                {cfg.DB_PREFIX}posts.ai_prompt_post AS ai_prompt_post,
                {cfg.DB_PREFIX}posts.img AS img,
                {cfg.DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                {cfg.DB_PREFIX}posts.channels AS channels,
                {cfg.DB_PREFIX}posts.published_at AS published_at,
                {cfg.DB_PREFIX}posts.published AS published,
                {cfg.DB_PREFIX}settings.ai_personality AS ai_personality,
                {cfg.DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                {cfg.DB_PREFIX}settings.ai_comment_prefix AS ai_comment_prefix,
                {cfg.DB_PREFIX}settings.openai_api_key AS openai_api_key,
                {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id,
                {cfg.DB_PREFIX}settings.linkedin_client_id AS linkedin_client_id,
                {cfg.DB_PREFIX}settings.linkedin_client_secret AS linkedin_client_secret,
                {cfg.DB_PREFIX}settings.linkedin_token AS linkedin_token,
                COUNT({cfg.DB_PREFIX}comments.post_id) AS comments_count,
                SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'facebook' THEN 1 ELSE 0 END) AS facebook_comments_count,
                SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count,
                SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'linkedin' THEN 1 ELSE 0 END) AS linkedin_comments_count

            FROM {cfg.DB_PREFIX}posts
                INNER JOIN {cfg.DB_PREFIX}settings ON {cfg.DB_PREFIX}posts.user_id = {cfg.DB_PREFIX}settings.user_id
                LEFT JOIN {cfg.DB_PREFIX}comments ON {cfg.DB_PREFIX}posts.id = {cfg.DB_PREFIX}comments.post_id

        WHERE {cfg.DB_PREFIX}posts.published = 1
            AND {cfg.DB_PREFIX}posts.task_complete = 0
            AND ({cfg.DB_PREFIX}posts.on_hold_until IS NULL OR {cfg.DB_PREFIX}posts.on_hold_until <= '{time_now}')
            AND {cfg.DB_PREFIX}posts.deleted_at is null

        LIMIT 0, 1
    """)

    for row in rows:

        if row['id'] is not None:
            channels = json.loads(row['channels'])

            if debug is True:
                print(
                    datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                    "Comments monitoring - Post ID:",
                    row['id']
                )

            # Per ogni canale selezionato (Facebook, Instagram, WordPress, ...)
            # recupero i commenti
            for i in channels:
                if (channels[i]['name'] == 'Facebook'
                    and channels[i]['on'] == '1'
                    and channels[i]['reply_on'] == '1'
                    and Decimal(row['facebook_comments_count'] or 0) < Decimal(channels[i]['reply_n'] or 0)):
                    facebook_comments = FacebookComment(data=row, debug=debug)
                    facebook_comments.get()

                if (channels[i]['name'] == 'Instagram'
                    and channels[i]['on'] == '1'
                    and channels[i]['reply_on'] == '1'
                    and Decimal(row['instagram_comments_count'] or 0) < Decimal(channels[i]['reply_n'] or 0)):
                    instagram_comments = InstagramComment(data=row, debug=debug)
                    instagram_comments.get()

                if (channels[i]['name'] == 'LinkedIn'
                    and channels[i]['on'] == '1'
                    and channels[i]['reply_on'] == '1'
                    and Decimal(row['linkedin_comments_count'] or 0) < Decimal(channels[i]['reply_n'] or 0)):
                    linkedin_comments = LinkedInComment(data=row, debug=debug)
                    linkedin_comments.get()

    mysql.close()

    if debug is True:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Comments get - END -------------------")
