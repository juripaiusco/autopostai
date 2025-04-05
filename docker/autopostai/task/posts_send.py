import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.newsletter import NewsletterPost
from task.posts.wordpress import WordPressPost
from task.ai_content_get import ai_content_get


def posts_send(debug = False):

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts sending - START -----------------")

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
                {cfg.DB_PREFIX}posts.ai_content AS ai_content,
                {cfg.DB_PREFIX}posts.title AS title,
                {cfg.DB_PREFIX}posts.img AS img,
                {cfg.DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                {cfg.DB_PREFIX}posts.channels AS channels,
                {cfg.DB_PREFIX}posts.published_at AS published_at,
                {cfg.DB_PREFIX}posts.published AS published,
                {cfg.DB_PREFIX}settings.ai_personality AS ai_personality,
                {cfg.DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                {cfg.DB_PREFIX}settings.openai_api_key AS openai_api_key,
                {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id,
                {cfg.DB_PREFIX}settings.wordpress_url AS wordpress_url,
                {cfg.DB_PREFIX}settings.wordpress_username AS wordpress_username,
                {cfg.DB_PREFIX}settings.wordpress_password AS wordpress_password,
                {cfg.DB_PREFIX}settings.wordpress_cat_id AS wordpress_cat_id,
                {cfg.DB_PREFIX}settings.mailchimp_api AS mailchimp_api,
                {cfg.DB_PREFIX}settings.mailchimp_datacenter AS mailchimp_datacenter,
                {cfg.DB_PREFIX}settings.mailchimp_list_id AS mailchimp_list_id,
                {cfg.DB_PREFIX}settings.mailchimp_from_name AS mailchimp_from_name,
                {cfg.DB_PREFIX}settings.mailchimp_from_email AS mailchimp_from_email,
                {cfg.DB_PREFIX}settings.mailchimp_template AS mailchimp_template,
                {cfg.DB_PREFIX}settings.mailchimp_template_cta AS mailchimp_template_cta

            FROM {cfg.DB_PREFIX}posts
                INNER JOIN {cfg.DB_PREFIX}settings
                    ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

        WHERE {cfg.DB_PREFIX}posts.published = 0
            AND {cfg.DB_PREFIX}posts.preview = 0
            AND {cfg.DB_PREFIX}posts.published_at <= "{cfg.CURRENT_TIME}"
            AND {cfg.DB_PREFIX}posts.deleted_at is null
    """)

    for row in rows:

        channels = json.loads(row['channels'])

        #########################################################
        #                                                       #
        #                 Collegamento al LLM                   #
        #                                                       #
        #########################################################

        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "LLM - create content")

        # Per ogni canale selezionato (Facebook, Instagram, WordPress, ...)
        # invio il post, con il testo creato dal LLM
        for i in channels:
            if (channels[i]['name'] == 'Facebook'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post sending")
                facebook_post = FacebookPost(data=row, debug=debug)
                channels[i]['id'], channels[i]['url'] = facebook_post.send(ai_content_get(
                    channelName=channels[i]['name'],
                    data=row,
                    debug=debug
                ))

            if (channels[i]['name'] == 'Instagram'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post sending")
                instagram_post = InstagramPost(data=row, debug=debug)
                channels[i]['id'], channels[i]['url'] = instagram_post.send(ai_content_get(
                    channelName=channels[i]['name'],
                    data=row,
                    debug=debug
                ))

            if (channels[i]['name'] == 'WordPress'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post sending")
                wordpress_post = WordPressPost(data=row, debug=debug)
                channels[i]['id'], channels[i]['url'] = wordpress_post.send(ai_content_get(
                    channelName=channels[i]['name'],
                    data=row,
                    debug=debug
                ))

            if (channels[i]['name'] == 'Newsletter'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post sending")
                newsletter_post = NewsletterPost(data=row, debug=debug)
                channels[i]['id'], channels[i]['url'] = newsletter_post.send(ai_content_get(
                    channelName=channels[i]['name'],
                    data=row,
                    debug=debug
                ))

        # Salvo gli ID del post nei vari canali
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET channels = %s WHERE id = %s",
            parameters=(json.dumps(channels), row['id'])
        )

        # Verifico che il post sia stato pubblicato e lo marchio come published
        ctrl_posts_sent(id=row['id'], debug=debug)

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts sending - END -------------------")


# Verifico se il post è stato pubblicato, verificando se gli ID
# dei post sono stati salvati. In caso tutti gli ID siano salvati
# e i canali siano su ON, il post è stato pubblicato.
def ctrl_posts_sent(id, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Eseguo una query per recuperare i channels
    rows = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}posts.id AS id,
                    {cfg.DB_PREFIX}posts.channels AS channels

                FROM {cfg.DB_PREFIX}posts

            WHERE {cfg.DB_PREFIX}posts.id = {id}
        """)

    published = 1

    # Verifico i channels e se l'ID del post è stato recuperato
    # se ho l'ID e il canale è impostato su ON, il post è stato
    # pubblicato.
    if rows is not None:
        channels = json.loads(rows[0]['channels'])

        for i in channels:
            if channels[i]['id'] is None and channels[i]['on'] == '1':
                published = 0

        if debug is True:
            print(
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                "Post ID:",
                id,
                "published:",
                published,
            )

    # Nel caso in cui il post sia pubblicato lo marchio come tale (published)
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET published = %s WHERE id = %s",
        parameters=(published, id)
    )

    mysql.close()
