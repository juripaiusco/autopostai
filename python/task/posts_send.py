import json
import config as cfg
from services.gpt import GPT
from datetime import datetime
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost

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
                {cfg.DB_PREFIX}posts.img AS img,
                {cfg.DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                {cfg.DB_PREFIX}posts.channels AS channels,
                {cfg.DB_PREFIX}posts.published_at AS published_at,
                {cfg.DB_PREFIX}posts.published AS published,
                {cfg.DB_PREFIX}settings.ai_personality AS ai_personality,
                {cfg.DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                {cfg.DB_PREFIX}settings.openai_api_key AS openai_api_key,
                {cfg.DB_PREFIX}settings.meta_page_id AS meta_page_id

            FROM {cfg.DB_PREFIX}posts
                INNER JOIN {cfg.DB_PREFIX}settings
                    ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

        WHERE {cfg.DB_PREFIX}posts.published = 0
            AND {cfg.DB_PREFIX}posts.published_at <= "{cfg.CURRENT_TIME}"
    """)

    for row in rows:

        channels = json.loads(row['channels'])

        base_post = BasePost(data=row)
        prompt = base_post.prompt_get()

        #########################################################
        #                                                       #
        #               Collegamento ad OpenAI                  #
        #                                                       #
        #########################################################

        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "OpenAi - create content")

        # Verifico che ci sia almeno un canale attivato per l'invio di post
        # se non ci sono canali attivati, non verrà fatta nessuna connessione
        # ad OpenAI
        connect_to_openai = 0
        for i in channels:
            if channels[i]['on'] == '1':
                connect_to_openai = 1

        # Mi connetto ad OpenAI
        if connect_to_openai == 1:
            if row['img_ai_check_on'] == '1':
                content = openai_generate(row, prompt, base_post.img_path_get())
            else:
                content = openai_generate(row, prompt)

        # Per ogni canale selezionato (Facebook, Instagram, WordPress, ...)
        # invio il post, con il testo creato da OpenAI
        for i in channels:
            if channels[i]['name'] == 'Facebook' and channels[i]['on'] == '1':
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Facebook - post sending")
                facebook_post = FacebookPost(data=row, debug=debug)
                facebook_post.send(content)

            if channels[i]['name'] == 'Instagram' and channels[i]['on'] == '1':
                if debug:
                    print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Instagram - post sending")
                instagram_post = InstagramPost(data=row)
                instagram_post.send(content)

        # Verifico che il post sia stato pubblicato e lo marchio come published
        ctrl_posts_sent(id=row['id'], debug=debug)

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Posts sending - END -------------------")

# In questa funziona mi collego ad OpenAI e recupero l'output
def openai_generate(data, prompt, img_path = None):
    # Classe OpenAI
    gpt = GPT(api_key=data['openai_api_key'])

    # Verifico se l'immagine è da inviare all'AI e se l'immagine esiste
    if img_path is not None:
        contenuto, tokens_used = gpt.generate(prompt, img_path)
    else:
        contenuto, tokens_used = gpt.generate(prompt)

    # ---------------------------------------------------------
    mysql = Mysql()
    mysql.connect()

    # Salvo il contenuto generato dall'AI
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
        parameters=(contenuto, data['id'])
    )

    # Salvo i token utilizzati per questo post
    mysql.query(f"""
                    INSERT INTO {cfg.DB_PREFIX}token_logs (
                        user_id,
                        type,
                        reference_id,
                        tokens_used,
                        created_at
                    ) VALUES (%s, %s, %s, %s, %s)
                """, (
        data['user_id'],
        "post",
        data['id'],
        tokens_used,
        cfg.CURRENT_TIME
    ))

    mysql.close()

    return contenuto

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

    published = 0

    # Verifico i channels e se l'ID del post è stato recuperato
    # se ho l'ID e il canale è impostato su ON, il post è stato
    # pubblicato.
    if rows is not None:
        channels = json.loads(rows[0]['channels'])

        published = 1

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
