import config as cfg
from datetime import datetime
from services.mysql import Mysql
import json
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.wordpress import WordPressPost

def posts_update(debug = False):

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts update - START --------------------")

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Database query")

    mysql = Mysql()
    mysql.connect()

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
                    {cfg.DB_PREFIX}settings.wordpress_password AS wordpress_password

                FROM {cfg.DB_PREFIX}posts
                    INNER JOIN {cfg.DB_PREFIX}settings
                        ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

            WHERE {cfg.DB_PREFIX}posts.updated = 2
                AND {cfg.DB_PREFIX}posts.deleted_at is null

            LIMIT 0, 1
        """)

    for row in rows:

        channels = json.loads(row['channels'])

        for i in channels:
            if (channels[i]['name'] == 'Facebook'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is not None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post updateding")
                # facebook_post = FacebookPost(data=row, debug=debug)
                # channels[i]['id'] = facebook_post.update(channels[i]['id'], row['ai_content'])

            if (channels[i]['name'] == 'Instagram'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is not None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post updateding")
                # instagram_post = InstagramPost(data=row, debug=debug)
                # channels[i]['id'] = instagram_post.update(channels[i]['id'], row['ai_content'])

            if (channels[i]['name'] == 'WordPress'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is not None):
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post updateding - ID:", channels[i]['id'])
                wordpress_post = WordPressPost(data=row, debug=debug)
                channels[i]['id_update'] = wordpress_post.update(channels[i]['id'], row['ai_content'])

        # Salvo gli ID dei post modificati nei vari canali
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET channels = %s WHERE id = %s",
            parameters=(json.dumps(channels), row['id'])
        )

        # Verifico che il post sia stato modificato e lo marchio come updated
        ctrl_posts_update(id=row['id'], debug=debug)

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts update - END ----------------------")


# Verifico che il post sia stato modificato correttamente da
# tutti i social, quindi controllo gli id restituiti se
# combaciano, poi marchio il post come modificato, così
# non verrà più processato
def ctrl_posts_update(id, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Eseguo una query per recuperare i channels
    rows = mysql.query(f"""
                SELECT  {cfg.DB_PREFIX}posts.id AS id,
                        {cfg.DB_PREFIX}posts.channels AS channels

                    FROM {cfg.DB_PREFIX}posts

                WHERE {cfg.DB_PREFIX}posts.id = {id}
            """)

    update = 1

    if rows is not None:
        channels = json.loads(rows[0]['channels'])

        for i in channels:
            if channels[i]['id'] != channels[i].get('id_update', None) and channels[i]['on'] == '1':
                update = 0

        if debug is True:
            print(
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                "Post ID:",
                id,
                "updated:",
                update,
            )

    # Nel caso in cui il post sia eliminato lo marchio come tale (updated)
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET updated = %s WHERE id = %s",
        parameters=(update, id)
    )

    mysql.close()
