import os
import pytz
import json
from tqdm import tqdm
from lib.gpt import GPT
from lib.meta import Meta
from decimal import Decimal
from lib.mysql import Mysql
from dotenv import load_dotenv
from datetime import datetime, timedelta

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

                WHERE {DB_PREFIX}posts.published = 0
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
            contenuto, tokens_used = gpt.generate(prompt, img_path)
        else:
            contenuto, tokens_used = gpt.generate(prompt)

        # Salvo il contenuto generato dall'AI
        mysql.query(
            query=f"UPDATE {DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
            parameters=(contenuto, row['id'])
        )

        # Salvo i token utilizzati per questo post
        mysql.query(f"""
                INSERT INTO {DB_PREFIX}token_logs (
                    user_id,
                    type,
                    reference_id,
                    tokens_used,
                    created_at
                ) VALUES (%s, %s, %s, %s, %s)
            """, (
            row['user_id'],
            "post",
            row['id'],
            tokens_used,
            CURRENT_TIME
        ))

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
                {DB_PREFIX}settings.meta_page_id AS meta_page_id,
                COUNT({DB_PREFIX}comments.post_id) AS comments_count,
                SUM(CASE WHEN {DB_PREFIX}comments.channel = 'facebook' THEN 1 ELSE 0 END) AS facebook_comments_count,
                SUM(CASE WHEN {DB_PREFIX}comments.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count

            FROM {DB_PREFIX}posts
                INNER JOIN {DB_PREFIX}settings ON {DB_PREFIX}posts.user_id = {DB_PREFIX}settings.user_id
                LEFT JOIN {DB_PREFIX}comments ON {DB_PREFIX}posts.id = {DB_PREFIX}comments.post_id

        WHERE {DB_PREFIX}posts.published = 1
            AND {DB_PREFIX}posts.task_complete = 0
            LIMIT 0, 1
    """
    rows = mysql.query(query)

    if debug is True:
        print("\nImport comments...\n")

    for row in rows:

        if row['id'] is not None:

            channels = json.loads(row['channels'])

            #########################################################
            #                                                       #
            #     Collegamento a Meta e importazione commenti       #
            #                                                       #
            #########################################################

            if row['meta_page_id'] is not None:

                ####### FACEBOOK #############
                # Verifico che il post abbia l'impostazione di replica
                if (channels['facebook']['reply_on'] == '1'
                    and row['facebook_comments_count'] < channels['facebook']['reply_n']):

                    # Collegamento a Facebook
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

                ####### INSTAGRAM #############
                # Verifico che il post abbia l'impostazione di replica
                if (channels['instagram']['reply_on'] == '1'
                    and row['instagram_comments_count'] < channels['instagram']['reply_n']):

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

            # Imposto il post "task_complete" nel caso abbia raggiunto i requisiti di risposta
            task_complete = 0

            if (Decimal(row['facebook_comments_count'] or 0) >= Decimal(channels['facebook']['reply_n'] or 0)
                and Decimal(row['instagram_comments_count'] or 0) >= Decimal(channels['instagram']['reply_n'] or 0)):
                task_complete = 1

            if Decimal(channels['facebook']['reply_n'] or 0) > 0 and channels['facebook']['reply_on'] == '0':
                task_complete = 1

            if Decimal(channels['instagram']['reply_n'] or 0) > 0 and channels['instagram']['reply_on'] == '0':
                task_complete = 1

            if task_complete == 1:
                mysql.query(
                    query=f"UPDATE {DB_PREFIX}posts SET task_complete = %s WHERE id = %s",
                    parameters=(task_complete, row['id'])
                )

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
                LIMIT 0, 1
        """
    rows = mysql.query(query)

    if debug is True:
        print("\nReply comments...\n")

    for row in rows:

        # Preparo il prompt da inviare al LLM
        prompt = ""

        if row['ai_personality']:
            prompt = prompt + "Immedesimati in questa persona:\n" + row['ai_personality'] + "\n\n"

        if row['ai_prompt_prefix']:
            prompt = prompt + row['ai_prompt_prefix'] + "\n\n"

        if row['channel']:
            prompt = prompt + f"È stato creato questo post su {row['channel']}:\n"

        if row['ai_content']:
            prompt = prompt + row['ai_content'] + "\n"
            prompt = prompt + "\n"

        # Adatto il tipo di proprietario del commento in base al canale social.
        # Questo perché con instagram inserendo la @ prima dello username taggherà
        # la risposta del commento all'utente
        if row['channel'] == 'instagram':
            prompt = prompt + f"@{row['from_name']} ha risposto con un commento:"

        # Adatto il tipo di proprietario del commento in base al canale social
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
        reply = None

        # Classe OpenAI
        if row['openai_api_key'] is not None:
            gpt = GPT(api_key=row['openai_api_key'])
            reply, tokens_used = gpt.generate(prompt)

            # Salvo i token utilizzati per questo post
            mysql.query(f"""
                            INSERT INTO {DB_PREFIX}token_logs (
                                user_id,
                                type,
                                reference_id,
                                tokens_used,
                                created_at
                            ) VALUES (%s, %s, %s, %s, %s)
                        """, (
                row['user_id'],
                "reply",
                row['id'],
                tokens_used,
                CURRENT_TIME
            ))

        #########################################################
        #                                                       #
        #                 Collegamento a Meta                   #
        #                                                       #
        #########################################################

        # Classe Meta
        if reply is not None and row['meta_page_id'] is not None:
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
#
# 1. posts_sending()
#    Vengono inviati tutti i post da inviare, cioè nel database ci sono dei
#    pronti per essere inviati, viene eseguita una query che li richiama e
#    vengono caricati nei vari canali.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Statisticamente non verranno pubblicati tutti i post allo stesso orario
#    quindi la query che recupera i post da inviare li prende tutti.
#
# 2. comments_get()
#    Per ogni post inviato viene verificata la presenza di nuovi commenti,
#    questi ultimi vengono scaricati e salvati nel database, questo per poter
#    creare una risposta pertinente all'utente che ha commentato, perché
#    vengono presi tutti i vari dati per creare una risposta.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Viene selezionato un post al minuto e che al suo interno ha gli ID di tutti
#    i canali utilizzati. In base alle impostazioni del canale vengono fatte
#    delle azioni, tra cui rispondere ai commenti.
#    Quando il numero dei commenti scaricati, corrisponde al numero dei commenti
#    massimo al quale il post può rispondere, il post viene marchiato come "task_complete",
#    in questo modo non verrà più selezionato dalla query iniziale per scaricare i
#    commenti.
#
# 3. comments_reply()
#    Invio della risposta al commento, in base al tipo di post e commento fatto,
#    viene creata una risposta pertinente, in base alle impostazioni create.
#    RAGIONAMENTO: ----------------------------------------------------------
#    Viene fatta una query, selezionando un commento alla volta, quindi un commento
#    al minuto, al quale viene data una risposta, una volta risposto il commento
#    risulterà risposto, perché avrà un reply e un reply_id.
#
#    IMPORTANTE:
#    Se viene impostato un limite di risposte, per adesso questo probabilmente non
#    verrà rispettato nei grandi numeri, perché vengono scaricati molti commenti,
#    ma non è ancora indicato un massimo di download. Bisogna prevedere un controllo
#    nei commenti del db, così da limitare le risposte.
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
