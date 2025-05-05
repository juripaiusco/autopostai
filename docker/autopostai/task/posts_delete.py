import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.linkedin import LinkedInPost
from task.posts.wordpress import WordPressPost
from task.posts.newsletter import NewsletterPost


def posts_delete(debug = False):

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts deleting - START -----------------")

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
                    {cfg.DB_PREFIX}posts.channels AS channels,
                    {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id,
                    {cfg.DB_PREFIX}settings.linkedin_client_id AS linkedin_client_id,
                    {cfg.DB_PREFIX}settings.linkedin_client_secret AS linkedin_client_secret,
                    {cfg.DB_PREFIX}settings.linkedin_token AS linkedin_token,
                    {cfg.DB_PREFIX}settings.wordpress_url AS wordpress_url,
                    {cfg.DB_PREFIX}settings.wordpress_username AS wordpress_username,
                    {cfg.DB_PREFIX}settings.wordpress_password AS wordpress_password,
                    {cfg.DB_PREFIX}settings.mailchimp_api AS mailchimp_api,
                    {cfg.DB_PREFIX}settings.mailchimp_datacenter AS mailchimp_datacenter,
                    {cfg.DB_PREFIX}settings.mailchimp_list_id AS mailchimp_list_id,
                    {cfg.DB_PREFIX}settings.mailchimp_from_name AS mailchimp_from_name,
                    {cfg.DB_PREFIX}settings.mailchimp_from_email AS mailchimp_from_email,
                    {cfg.DB_PREFIX}settings.mailchimp_template AS mailchimp_template,
                    {cfg.DB_PREFIX}settings.brevo_api AS brevo_api,
                    {cfg.DB_PREFIX}settings.brevo_list_id AS brevo_list_id,
                    {cfg.DB_PREFIX}settings.brevo_from_name AS brevo_from_name,
                    {cfg.DB_PREFIX}settings.brevo_from_email AS brevo_from_email,
                    {cfg.DB_PREFIX}settings.brevo_template AS brevo_template,
                    {cfg.DB_PREFIX}settings.brevo_template_cta AS brevo_template_cta

                FROM {cfg.DB_PREFIX}posts
                    INNER JOIN {cfg.DB_PREFIX}settings
                        ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

            WHERE {cfg.DB_PREFIX}posts.published = 1
                AND {cfg.DB_PREFIX}posts.deleted = 0
                AND {cfg.DB_PREFIX}posts.deleted_at <= "{cfg.CURRENT_TIME}"
        """)

    for row in rows:
        channels = json.loads(row['channels'])

        for i in channels:
            if channels[i]['name'] == 'Facebook' and channels[i]['on'] == '1':
                if channels[i]['id'] != channels[i].get('id_del', None):
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                    facebook_post = FacebookPost(data=row, debug=debug)
                    channels[i]['id_del'] = facebook_post.delete(channels[i]['id'])

            if channels[i]['name'] == 'Instagram' and channels[i]['on'] == '1':
                if channels[i]['id'] != channels[i].get('id_del', None):
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                        print("The Instagram API does not allow for the deletion of posts")
                    # instagram_post = InstagramPost(data=row, debug=debug)
                    # channels[i]['id_del'] = instagram_post.delete(channels[i]['id'])
                    channels[i]['id_del'] = channels[i]['id']

            if channels[i]['name'] == 'LinkedIn' and channels[i]['on'] == '1':
                if channels[i]['id'] != channels[i].get('id_del', None):
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                    linkedin_post = LinkedInPost(data=row, debug=debug)
                    channels[i]['id_del'] = linkedin_post.delete(channels[i]['id'])

            if channels[i]['name'] == 'WordPress' and channels[i]['on'] == '1':
                if channels[i]['id'] != channels[i].get('id_del', None):
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                    wordpress_post = WordPressPost(data=row, debug=debug)
                    channels[i]['id_del'] = wordpress_post.delete(channels[i]['id'])

            if channels[i]['name'] == 'Newsletter' and channels[i]['on'] == '1':
                if channels[i]['id'] != channels[i].get('id_del', None):
                    if debug:
                        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                              channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                    newsletter_post = NewsletterPost(data=row, debug=debug)
                    channels[i]['id_del'] = newsletter_post.delete(channels[i]['id'])

        # Salvo gli ID dei post eliminati nei vari canali
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET channels = %s WHERE id = %s",
            parameters=(json.dumps(channels), row['id'])
        )

        # Verifico che il post sia stato eliminato e lo marchio come deleted
        ctrl_posts_deleted(id=row['id'], debug=debug)

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts deleting - END -------------------")


# Verifico che il post sia stato eliminato correttamente da
# tutti i social, quindi controllo gli id restituiti se
# combaciano, poi marchio il post come eliminato, così
# non verrà più processato
def ctrl_posts_deleted(id, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Eseguo una query per recuperare i channels
    rows = mysql.query(f"""
                SELECT  {cfg.DB_PREFIX}posts.id AS id,
                        {cfg.DB_PREFIX}posts.channels AS channels

                    FROM {cfg.DB_PREFIX}posts

                WHERE {cfg.DB_PREFIX}posts.id = {id}
            """)

    deleted = 1

    if rows is not None:
        channels = json.loads(rows[0]['channels'])

        for i in channels:
            if channels[i]['id'] != channels[i].get('id_del', None) and channels[i]['on'] == '1':
                deleted = 0

        if debug is True:
            print(
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                "Post ID:",
                id,
                "deleted:",
                deleted,
            )

    # Nel caso in cui il post sia eliminato lo marchio come tale (deleted)
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET deleted = %s WHERE id = %s",
        parameters=(deleted, id)
    )

    mysql.close()
