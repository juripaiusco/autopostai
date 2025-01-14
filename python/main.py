import os
from dotenv import load_dotenv
from lib.gpt import GPT
from lib.meta import Meta
from lib.mysql import Mysql
from datetime import datetime, timedelta
import pytz
from tqdm import tqdm
import json

load_dotenv(dotenv_path=".laravel-env")

# Fuso orario locale (ad esempio, Europa/Roma)
LOCAL_TIMEZONE = pytz.timezone('Europe/Rome')

# Ottieni la data attuale
CURRENT_TIME = datetime.now(LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S')

DB_PREFIX = os.getenv('DB_PREFIX')

def posts_sending(debug = False):
    # Recupero i dati dal database per generare i Post
    mysql = Mysql()
    mysql.connect()

    #########################################################
    #                                                       #
    #                     Query MySQL                       #
    #                                                       #
    #########################################################

    query = f"""
                SELECT  {DB_PREFIX}posts.id AS id,
                        {DB_PREFIX}posts.user_id AS user_id,
                        {DB_PREFIX}posts.ai_prompt_post AS ai_prompt_post,
                        {DB_PREFIX}posts.img AS img,
                        {DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                        {DB_PREFIX}posts.channels AS channels,
                        {DB_PREFIX}posts.published_at AS published_at,
                        {DB_PREFIX}posts.published AS published,
                        {DB_PREFIX}settings.ai_personality AS ai_personality,
                        {DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                        {DB_PREFIX}settings.openai_api_key AS openai_api_key,
                        {DB_PREFIX}settings.meta_page_id AS meta_page_id

                    FROM {DB_PREFIX}posts
                        INNER JOIN {DB_PREFIX}settings ON {DB_PREFIX}settings.user_id = {DB_PREFIX}posts.user_id

                WHERE {DB_PREFIX}posts.published IS NULL
                    AND {DB_PREFIX}posts.published_at <= "{CURRENT_TIME}"
            """
    rows = mysql.query(query)

    if debug is True:
        print("\nPosts sending...\n")

    # Leggo tutti i post
    for row in rows:

        channels = json.loads(row['channels'])

        #########################################################
        #                                                       #
        #         Creazione del Prompt e dei Media              #
        #                                                       #
        #########################################################

        # Creo il prompt
        prompt = ""

        if row['ai_personality']:
            prompt = prompt + f"{row['ai_personality']}\n\n"

        if row['ai_prompt_prefix']:
            prompt = prompt + f"{row['ai_prompt_prefix']}\n\n"

        if row['ai_prompt_post']:
            prompt = prompt + "#Guidelines\n\n"
            prompt = prompt + f"{row['ai_prompt_post']}"

        # Definisco i percorsi delle immagini
        img_path = f"./storage/app/public/posts/{row['id']}/{row['img']}"
        img_url = f"{os.getenv('APP_URL')}/storage/posts/{row['id']}/{row['img']}"

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #               Collegamento ad OpenAI                  #
        #                                                       #
        #########################################################

        # Classe OpenAI
        gpt = GPT(api_key=row['openai_api_key'])

        # Verifico se l'immagine è da inviare all'AI e se l'immagine esiste
        if row['img_ai_check_on'] == '1' and row['img']:
            contenuto = gpt.generate(prompt, img_path)
        else:
            contenuto = gpt.generate(prompt)

        # Salvo il contenuto generato dall'AI
        mysql.query(
            query=f"UPDATE {DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
            parameters=(contenuto, row['id'])
        )

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #                 Collegamento a Meta                   #
        #                                                       #
        #########################################################

        # Classe Meta
        meta = Meta(page_id=row['meta_page_id'])

        channels['facebook']['id'] = None
        channels['instagram']['id'] = None

        # Verifico se l'immagine è stata caricata e la invio ai canali scelti
        if row['img']:

            if channels['facebook']['on'] == '1':
                channels['facebook']['id'] = meta.fb_generate_post(contenuto, img_path)

                if debug is True:
                    print("\nFacebok post id: ", channels['facebook']['id'])

                mysql.query(
                    query=f"UPDATE {DB_PREFIX}posts SET channels = %s WHERE id = %s",
                    parameters=(json.dumps(channels), row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

            if channels['instagram']['on'] == '1':
                channels['instagram']['id'] = meta.ig_generate_post(contenuto, img_url)

                if debug is True:
                    print("\nInstagram post id: ", channels['instagram']['id'])

                mysql.query(
                    query=f"UPDATE {DB_PREFIX}posts SET channels = %s WHERE id = %s",
                    parameters=(json.dumps(channels), row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

        else:

            if channels['facebook']['on'] == '1':
                channels['facebook']['id'] = meta.fb_generate_post(contenuto)

                if debug is True:
                    print("\nFacebok post id: ", channels['facebook']['id'])

                mysql.query(
                    query=f"UPDATE {DB_PREFIX}posts SET channels = %s WHERE id = %s",
                    parameters=(json.dumps(channels), row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #  Imposto il post come pubblicato e salvo i parametri  #
        #                                                       #
        #########################################################

        if channels['facebook']['id'] or channels['instagram']['id'] is not None:
            mysql.query(
                query=f"UPDATE {DB_PREFIX}posts SET published = %s WHERE id = %s",
                parameters=(1, row['id'])
            )

    mysql.close()


def comments_get(debug = False):
    mysql = Mysql()
    mysql.connect()

    query = f"""
        SELECT  {DB_PREFIX}posts.id AS id,
                {DB_PREFIX}posts.user_id AS user_id,
                {DB_PREFIX}posts.ai_prompt_post AS ai_prompt_post,
                {DB_PREFIX}posts.img AS img,
                {DB_PREFIX}posts.img_ai_check_on AS img_ai_check_on,
                {DB_PREFIX}posts.channels AS channels,
                {DB_PREFIX}posts.published_at AS published_at,
                {DB_PREFIX}posts.published AS published,
                {DB_PREFIX}settings.ai_personality AS ai_personality,
                {DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                {DB_PREFIX}settings.openai_api_key AS openai_api_key,
                {DB_PREFIX}settings.meta_page_id AS meta_page_id

            FROM {DB_PREFIX}posts
                INNER JOIN {DB_PREFIX}settings ON {DB_PREFIX}settings.user_id = {DB_PREFIX}posts.user_id

        WHERE {DB_PREFIX}posts.published = '1'
            LIMIT 0, 10
    """
    rows = mysql.query(query)

    if debug is True:
        print("\nImport comments...\n")

    for row in rows:

        channels = json.loads(row['channels'])

        #########################################################
        #                                                       #
        #     Collegamento a Meta e importazione commenti       #
        #                                                       #
        #########################################################

        # Collegamento a Facebook
        if row['meta_page_id'] is not None:
            meta = Meta(page_id=row['meta_page_id'])
            comments = meta.fb_get_comments(channels['facebook']['id'])

            if comments.get('error') is None:
                for comment in comments['data']:
                    # Estrarre e convertire la data
                    raw_date = comment['created_time']  # es: '2024-12-31T16:09:53+0000'

                    # Parsing della data con il fuso orario originale
                    facebook_date = datetime.strptime(raw_date, '%Y-%m-%dT%H:%M:%S%z')
                    original_date = facebook_date + timedelta(hours=1)
                    # Conversione in un altro fuso orario (es. UTC)
                    utc_date = original_date.astimezone(pytz.UTC)
                    # Formattazione della data convertita
                    converted_date = utc_date.strftime('%Y-%m-%d %H:%M:%S')

                    mysql.query(f"""
                            INSERT IGNORE INTO {DB_PREFIX}comments (
                                post_id,
                                channel,
                                from_id,
                                from_name,
                                message_id,
                                message,
                                message_created_time
                            ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, (
                        row['id'],
                        "facebook",
                        comment['from']['id'],
                        comment['from']['name'],
                        comment['id'],
                        comment['message'],
                        converted_date
                    ))

            # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

            # Collegamento ad Instagram
            meta = Meta(page_id=row['meta_page_id'])
            comments = meta.ig_get_comments(channels['instagram']['id'])

            if comments.get('error') is None:
                for comment in comments['data']:
                    # Estrarre e convertire la data
                    raw_date = comment['timestamp']  # es: '2024-12-31T16:09:53+0000'

                    # Parsing della data con il fuso orario originale
                    facebook_date = datetime.strptime(raw_date, '%Y-%m-%dT%H:%M:%S%z')
                    original_date = facebook_date + timedelta(hours=1)
                    # Conversione in un altro fuso orario (es. UTC)
                    utc_date = original_date.astimezone(pytz.UTC)
                    # Formattazione della data convertita
                    converted_date = utc_date.strftime('%Y-%m-%d %H:%M:%S')

                    mysql.query(f"""
                            INSERT IGNORE INTO {DB_PREFIX}comments (
                                post_id,
                                channel,
                                from_id,
                                from_name,
                                message_id,
                                message,
                                message_created_time
                            ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, (
                        row['id'],
                        "instagram",
                        comment['from']['id'],
                        comment['from']['username'],
                        comment['id'],
                        comment['text'],
                        converted_date
                    ))

            # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    mysql.close()


def comments_reply(debug = False):
    mysql = Mysql()
    mysql.connect()

    query = f"""
            SELECT  {DB_PREFIX}comments.id AS id,
                    {DB_PREFIX}comments.channel AS channel,
                    {DB_PREFIX}comments.from_name AS from_name,
                    {DB_PREFIX}comments.message_id AS message_id,
                    {DB_PREFIX}comments.message AS message,
                    {DB_PREFIX}posts.ai_content AS ai_content,
                    {DB_PREFIX}settings.ai_personality AS ai_personality,
                    {DB_PREFIX}settings.ai_prompt_prefix AS ai_prompt_prefix,
                    {DB_PREFIX}settings.openai_api_key AS openai_api_key,
                    {DB_PREFIX}settings.meta_page_id AS meta_page_id

                FROM {DB_PREFIX}comments
                    INNER JOIN {DB_PREFIX}posts ON {DB_PREFIX}posts.id = {DB_PREFIX}comments.post_id
                    INNER JOIN {DB_PREFIX}users ON {DB_PREFIX}users.id = {DB_PREFIX}posts.user_id
                    INNER JOIN {DB_PREFIX}settings ON {DB_PREFIX}settings.user_id = {DB_PREFIX}users.id

            WHERE {DB_PREFIX}comments.reply IS NULL
        """
    rows = mysql.query(query)

    if debug is True:
        print("\nReply comments...\n")

    for row in rows:
        prompt = ""

        if row['ai_personality']:
            prompt = prompt + "Immedesimati in questa persona:\n" + row['ai_personality'] + "\n\n"

        if row['ai_prompt_prefix']:
            prompt = prompt + row['ai_prompt_prefix'] + "\n\n"

        prompt = prompt + f"È stato creato questo post su {row['channel']}:\n"
        prompt = prompt + row['ai_content'] + "\n"
        prompt = prompt + "\n"

        if row['channel'] == 'instagram':
            prompt = prompt + f"@{row['from_name']} ha risposto con un commento:"

        if row['channel'] == 'facebook':
            prompt = prompt + f"{row['from_name']} ha risposto con un commento:"

        prompt = prompt + row['from_name'] + " ha risposto con un commento:"
        prompt = prompt + "\n"
        prompt = prompt + row['message'] + "\n"
        prompt = prompt + "\n"
        prompt = prompt + """
            Impersonando la persona all'inizio, scrivi un risposta breve, positiva ed inclusiva,
            che faccia felice chi la legge. Utilizza un tono informale.
            """

        #########################################################
        #                                                       #
        #               Collegamento ad OpenAI                  #
        #                                                       #
        #########################################################

        reply_id = None

        # Classe OpenAI
        gpt = GPT(api_key=row['openai_api_key'])
        reply = gpt.generate(prompt)

        #########################################################
        #                                                       #
        #                 Collegamento a Meta                   #
        #                                                       #
        #########################################################

        # Classe Meta
        meta = Meta(page_id=row['meta_page_id'])

        if row['channel'] == "facebook":
            reply_id = meta.fb_reply_comments(row['message_id'], reply)

        if row['channel'] == "instagram":
            reply_id = meta.ig_reply_comments(row['message_id'], reply)

        #########################################################
        #                                                       #
        #           Imposto il commento come replicato          #
        #                                                       #
        #########################################################

        if reply_id is not None:
            mysql.query(
                query=f"UPDATE {DB_PREFIX}comments SET reply_id = %s, reply = %s, reply_created_time = %s WHERE id = %s",
                parameters=(reply_id['id'], reply, CURRENT_TIME, row['id'])
            )

    mysql.close()


#
# Questa funzione dev'essere richiamata da un contrab ogni minuto.
#
# Al suo interno main richiama 3 funzioni principali:
# 1. posts_sending()
#    Vengono inviati tutti i post da inviare, cioè nel database ci sono dei
#    pronti per essere inviati, viene eseguita una query che li richiama e
#    vengono caricati nei vari canali.
#
# 2. comments_get()
#    Per ogni post inviato viene verificata la presenza di nuovi commenti,
#    questi ultimi vengono scaricati e salvati nel database, questo per poter
#    creare una risposta pertinente all'utente che ha commentato.
#
# 3. comments_reply()
#    Invio della risposta al commento, in base al tipo di post e commento fatto,
#    viene creata una risposta pertinente, in base alle impostazioni create.
#
def main():
    debug = True

    progress_bar_desc = 'AutoPostAI'
    data_list = [
        'Sending posts',
        'Get the comments',
        'Reply to comments',
    ]

    with tqdm(total=len(data_list), desc=progress_bar_desc, ncols=None) as progress_bar:
        progress_bar.set_description(f"{progress_bar_desc} - {data_list[0]}")
        posts_sending(debug=debug)
        progress_bar.update(1)

        progress_bar.set_description(f"{progress_bar_desc} - {data_list[1]}")
        comments_get(debug=debug)
        progress_bar.update(1)

        progress_bar.set_description(f"{progress_bar_desc} - {data_list[2]}")
        comments_reply(debug=debug)
        progress_bar.update(1)


if __name__ == "__main__":
    main()
