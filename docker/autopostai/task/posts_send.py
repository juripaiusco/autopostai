import json

import requests

import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.linkedin import LinkedInPost
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
                {cfg.DB_PREFIX}settings.linkedin_client_id AS linkedin_client_id,
                {cfg.DB_PREFIX}settings.linkedin_client_secret AS linkedin_client_secret,
                {cfg.DB_PREFIX}settings.linkedin_token AS linkedin_token,
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
                {cfg.DB_PREFIX}settings.mailchimp_template_cta AS mailchimp_template_cta,
                {cfg.DB_PREFIX}settings.brevo_api AS brevo_api,
                {cfg.DB_PREFIX}settings.brevo_list_id AS brevo_list_id,
                {cfg.DB_PREFIX}settings.brevo_from_name AS brevo_from_name,
                {cfg.DB_PREFIX}settings.brevo_from_email AS brevo_from_email,
                {cfg.DB_PREFIX}settings.brevo_template AS brevo_template,
                {cfg.DB_PREFIX}settings.brevo_template_cta AS brevo_template_cta

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

            if (channels[i]['name'] == 'LinkedIn'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post sending")
                linkedin_post = LinkedInPost(data=row, debug=debug)
                channels[i]['id'], channels[i]['url'] = linkedin_post.send(ai_content_get(
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
                channels[i]['id'], channels[i]['url'], channels[i]['gallery_html'] = wordpress_post.send(ai_content_get(
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
                    {cfg.DB_PREFIX}posts.user_id AS user_id,
                    {cfg.DB_PREFIX}posts.created_by_user_id AS created_by_user_id,
                    {cfg.DB_PREFIX}posts.title AS title,
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

    # Salvo le notifiche una volta che il post è pubblicato
    if published == 1 and rows is not None:
        # Verifico che l'utente sia registrato, altrimenti si creerebbero notifiche
        # anche per utente non registrati alle push notification.
        subscription = mysql.query(f"""
                    SELECT  {cfg.DB_PREFIX}push_subscriptions.id AS id

                        FROM {cfg.DB_PREFIX}push_subscriptions

                    WHERE {cfg.DB_PREFIX}push_subscriptions.subscribable_type = "App\\\\Models\\\\User"
                        AND {cfg.DB_PREFIX}push_subscriptions.subscribable_id = {rows[0]['created_by_user_id']}

                    LIMIT 1
                """)

        if subscription and len(subscription) > 0:
            # Inserisco la notifica push da inviare all'autore del post
            mysql.query(f"""
                                            INSERT INTO {cfg.DB_PREFIX}push_notifications (
                                                user_id,
                                                title,
                                                body,
                                                url,
                                                created_at
                                            ) VALUES (%s, %s, %s, %s, %s)
                                        """, (
                rows[0]['created_by_user_id'],
                rows[0]['title'],
                'Post inviato',
                f"{cfg.URL}/posts/show/{rows[0]['id']}",
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S')
            ))

            print(" " * 19, "***")
            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                  "Push notification - START")
            print(" " * 19, "User ID to notify:", rows[0]['created_by_user_id'])
            print(" " * 19, "Title to notify:", rows[0]['title'])

            # Invio le notifiche
            if "localhost" in cfg.URL:
                print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                      "Push notification not send because LOCALHOST")
            else:
                url_send_notifications = f"{cfg.URL}/notifications/send-to-specific-users"
                response = requests.get(url_send_notifications)

                # Controlla se la richiesta è andata a buon fine
                if response.status_code == 200:
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              "Push notification sent")
                else:
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              "Errore:", response.status_code)

            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                  "Push notification - END")
            print(" " * 19, "***")

    mysql.close()
