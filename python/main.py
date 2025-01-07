import os
from dotenv import load_dotenv
from lib.gpt import GPT
from lib.meta import Meta
from lib.mysql import Mysql
from datetime import datetime, timedelta
import pytz
from tqdm import tqdm

load_dotenv(dotenv_path=".laravel-env")

# Fuso orario locale (ad esempio, Europa/Roma)
LOCAL_TIMEZONE = pytz.timezone('Europe/Rome')

# Ottieni la data attuale
CURRENT_TIME = datetime.now(LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S')

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
                SELECT  autopostai_posts.id AS id,
                        autopostai_posts.user_id AS user_id,
                        autopostai_posts.ai_prompt_post AS ai_prompt_post,
                        autopostai_posts.img AS img,
                        autopostai_posts.img_ai_check_on AS img_ai_check_on,
                        autopostai_posts.meta_facebook_on AS meta_facebook_on,
                        autopostai_posts.meta_instagram_on AS meta_instagram_on,
                        autopostai_posts.wordpress_on AS wordpress_on,
                        autopostai_posts.newsletter_on AS newsletter_on,
                        autopostai_posts.published_at AS published_at,
                        autopostai_posts.published AS published,
                        autopostai_settings.ai_personality AS ai_personality,
                        autopostai_settings.ai_prompt_prefix AS ai_prompt_prefix,
                        autopostai_settings.openai_api_key AS openai_api_key,
                        autopostai_settings.meta_page_id AS meta_page_id

                    FROM autopostai_posts
                        INNER JOIN autopostai_settings ON autopostai_settings.user_id = autopostai_posts.user_id

                WHERE autopostai_posts.published IS NULL
                    AND autopostai_posts.published_at <= "{CURRENT_TIME}"
            """
    rows = mysql.query(query)

    if debug is True:
        print("\nPosts sending...\n")

    # Leggo tutti i post
    for row in rows:

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
            query="UPDATE autopostai_posts SET ai_content = %s WHERE id = %s",
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

        fb_post_id = None
        ig_post_id = None

        # Verifico se l'immagine è stata caricata e la invio ai canali scelti
        if row['img']:

            if row['meta_facebook_on'] == '1':
                fb_post_id = meta.fb_generate_post(contenuto, img_path)

                if debug is True:
                    print("\nFacebok post id: ", fb_post_id)

                mysql.query(
                    query="UPDATE autopostai_posts SET meta_facebook_id = %s WHERE id = %s",
                    parameters=(fb_post_id, row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

            if row['meta_instagram_on'] == '1':
                ig_post_id = meta.ig_generate_post(contenuto, img_url)

                if debug is True:
                    print("\nInstagram post id: ", ig_post_id)

                mysql.query(
                    query="UPDATE autopostai_posts SET meta_instagram_id = %s WHERE id = %s",
                    parameters=(ig_post_id, row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

        else:

            if row['meta_facebook_on'] == '1':
                fb_post_id = meta.fb_generate_post(contenuto)

                if debug is True:
                    print("\nFacebok post id: ", fb_post_id)

                mysql.query(
                    query="UPDATE autopostai_posts SET meta_facebook_id = %s WHERE id = %s",
                    parameters=(fb_post_id, row['id'])
                )

                if debug is True:
                    print("\n- - - - - -\n")

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #  Imposto il post come pubblicato e salvo i parametri  #
        #                                                       #
        #########################################################

        if fb_post_id or ig_post_id is not None:
            mysql.query(
                query="UPDATE autopostai_posts SET published = %s WHERE id = %s",
                parameters=(1, row['id'])
            )

    mysql.close()


def comments_get(debug = False):
    mysql = Mysql()
    mysql.connect()

    query = f"""
        SELECT  autopostai_posts.id AS id,
                autopostai_posts.user_id AS user_id,
                autopostai_posts.ai_prompt_post AS ai_prompt_post,
                autopostai_posts.img AS img,
                autopostai_posts.img_ai_check_on AS img_ai_check_on,
                autopostai_posts.meta_facebook_id AS meta_facebook_id,
                autopostai_posts.meta_instagram_id AS meta_instagram_id,
                autopostai_posts.wordpress_id AS wordpress_id,
                autopostai_posts.newsletter_id AS newsletter_id,
                autopostai_posts.published_at AS published_at,
                autopostai_posts.published AS published,
                autopostai_settings.ai_personality AS ai_personality,
                autopostai_settings.ai_prompt_prefix AS ai_prompt_prefix,
                autopostai_settings.openai_api_key AS openai_api_key,
                autopostai_settings.meta_page_id AS meta_page_id

            FROM autopostai_posts
                INNER JOIN autopostai_settings ON autopostai_settings.user_id = autopostai_posts.user_id

        WHERE autopostai_posts.published = '1'
            LIMIT 0, 10
    """
    rows = mysql.query(query)

    if debug is True:
        print("\nImport comments...\n")

    for row in rows:

        #########################################################
        #                                                       #
        #     Collegamento a Meta e importazione commenti       #
        #                                                       #
        #########################################################

        # Collegamento a Facebook
        meta = Meta(page_id=row['meta_page_id'])
        comments = meta.fb_get_comments(row['meta_facebook_id'])

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
                        INSERT IGNORE INTO autopostai_comments (
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
        comments = meta.ig_get_comments(row['meta_instagram_id'])

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
                        INSERT IGNORE INTO autopostai_comments (
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
            SELECT  autopostai_comments.id AS id,
                    autopostai_comments.channel AS channel,
                    autopostai_comments.from_name AS from_name,
                    autopostai_comments.message_id AS message_id,
                    autopostai_comments.message AS message,
                    autopostai_posts.ai_content AS ai_content,
                    autopostai_settings.ai_personality AS ai_personality,
                    autopostai_settings.ai_prompt_prefix AS ai_prompt_prefix,
                    autopostai_settings.openai_api_key AS openai_api_key,
                    autopostai_settings.meta_page_id AS meta_page_id

                FROM autopostai_comments
                    INNER JOIN autopostai_posts ON autopostai_posts.id = autopostai_comments.post_id
                    INNER JOIN autopostai_users ON autopostai_users.id = autopostai_posts.user_id
                    INNER JOIN autopostai_settings ON autopostai_settings.user_id = autopostai_users.id

            WHERE autopostai_comments.reply IS NULL
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
                query="UPDATE autopostai_comments SET reply_id = %s, reply = %s, reply_created_time = %s WHERE id = %s",
                parameters=(reply_id['id'], reply, CURRENT_TIME, row['id'])
            )

    mysql.close()


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
