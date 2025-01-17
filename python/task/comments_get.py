import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.comments.facebook import FacebookComment
from task.comments.instagram import InstagramComment
from decimal import Decimal

def comments_get(debug = False):

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Comments get - START -----------------")

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Database query")

    mysql = Mysql()
    mysql.connect()

    #########################################################
    #                                                       #
    #                     Query MySQL                       #
    #                                                       #
    #########################################################

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
                {cfg.DB_PREFIX}settings.openai_api_key AS openai_api_key,
                {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id,
                COUNT({cfg.DB_PREFIX}comments.post_id) AS comments_count,
                SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'facebook' THEN 1 ELSE 0 END) AS facebook_comments_count,
                SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count

            FROM {cfg.DB_PREFIX}posts
                INNER JOIN {cfg.DB_PREFIX}settings ON {cfg.DB_PREFIX}posts.user_id = {cfg.DB_PREFIX}settings.user_id
                LEFT JOIN {cfg.DB_PREFIX}comments ON {cfg.DB_PREFIX}posts.id = {cfg.DB_PREFIX}comments.post_id

        WHERE {cfg.DB_PREFIX}posts.published = 1
            AND {cfg.DB_PREFIX}posts.task_complete = 0
            LIMIT 0, 1
    """)

    for row in rows:

        if row['id'] is not None:
            channels = json.loads(row['channels'])

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

        #########################################################
        #                                                       #
        #     INSERIRE RAGIONAMENTO PER task_copmlete           #
        #                                                       #
        #########################################################

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Comments get - END -------------------")
