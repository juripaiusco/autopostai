import json
import config as cfg
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.wordpress import WordPressPost
from task.ai_generate import ai_generate


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
                {cfg.DB_PREFIX}settings.wordpress_password AS wordpress_password

            FROM {cfg.DB_PREFIX}posts
                INNER JOIN {cfg.DB_PREFIX}settings
                    ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

        WHERE {cfg.DB_PREFIX}posts.published = 0
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
                channels[i]['id'] = facebook_post.send(ai_content_get(
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
                channels[i]['id'] = instagram_post.send(ai_content_get(
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


# Creo il contenuto tramite LLM e poi salvo il contenuto del post,
# viene passato la tipologia di post e in base alla tipologia viene
# modificato il prompt.
def ai_content_get(channelName, data, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Verifico che il contenuto sia già stato creato dal LLM
    row = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}posts.ai_content AS ai_content
                FROM {cfg.DB_PREFIX}posts
            WHERE {cfg.DB_PREFIX}posts.id = {data['id']}
        """)

    if row[0]['ai_content'] is not None:
        return row[0]['ai_content']

    base_post = BasePost(data=data)
    prompt = base_post.prompt_get()

    if channelName == 'WordPress':
        wordpress_post = WordPressPost(data=data)
        prompt = wordpress_post.prompt_get()

    # Mi connetto al LLM
    content = ai_generate(
        data=data,
        prompt=prompt,
        img_path=base_post.img_path_get() if data['img_ai_check_on'] == '1' else None,
        type="post",
        debug=debug
    )

    # Salvo il contenuto generato dall'AI
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
        parameters=(content, data['id'])
    )

    mysql.close()

    return content


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
