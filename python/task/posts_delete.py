import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.wordpress import WordPressPost


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
                    {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id

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
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                facebook_post = FacebookPost(data=row, debug=debug)
                channels[i]['id_del'] = facebook_post.delete(channels[i]['id'])

            if channels[i]['name'] == 'Instagram' and channels[i]['on'] == '1':
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                          channels[i]['name'], "- post deleting - ID:", channels[i]['id'])
                instagram_post = InstagramPost(data=row, debug=debug)
                channels[i]['id_del'] = instagram_post.delete(channels[i]['id'])

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
