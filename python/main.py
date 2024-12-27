import os
from dotenv import load_dotenv
from lib.gpt import GPT
from lib.meta import Meta
from lib.mysql import Mysql
from datetime import datetime
import pytz

load_dotenv(dotenv_path=".laravel-env")

def main():

    # Recupero i dati dal database per generare i Post
    mysql = Mysql()
    mysql.connect()

    #########################################################
    #                                                       #
    #                     Query MySQL                       #
    #                                                       #
    #########################################################

    # Fuso orario locale (ad esempio, Europa/Roma)
    local_timezone = pytz.timezone('Europe/Rome')

    # Ottieni la data attuale
    current_time = datetime.now(local_timezone).strftime('%Y-%m-%d %H:%M:%S')

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
                AND autopostai_posts.published_at <= "{current_time}"
        """
    rows = mysql.query(query)
    print("\n- - - - - -\n")

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

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #                 Collegamento a Meta                   #
        #                                                       #
        #########################################################

        # Classe Meta
        meta = Meta(page_id=row['meta_page_id'])

        # Verifico se l'immagine è stata caricata e la invio ai canali scelti
        if row['img']:

            if row['meta_facebook_on'] == '1':
                fb_post_id = meta.fb_generate_post(contenuto, img_path)
                print("\nFacebok post id: ", fb_post_id)
                mysql.query(
                    query="UPDATE autopostai_posts SET meta_facebook_id = %s WHERE id = %s",
                    parameters=(fb_post_id, row['id'])
                )
                print("\n- - - - - -\n")

            if row['meta_instagram_on'] == '1':
                ig_post_id = meta.ig_generate_post(contenuto, img_url)
                print("\nInstagram post id: ", ig_post_id)
                mysql.query(
                    query="UPDATE autopostai_posts SET meta_instagram_id = %s WHERE id = %s",
                    parameters=(ig_post_id, row['id'])
                )
                print("\n- - - - - -\n")

        else:

            if row['meta_facebook_on'] == '1':
                fb_post_id = meta.fb_generate_post(contenuto)
                mysql.query(
                    query="UPDATE autopostai_posts SET meta_facebook_id = %s WHERE id = %s",
                    parameters=(fb_post_id, row['id'])
                )
                print("\n- - - - - -\n")

        # - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        #########################################################
        #                                                       #
        #          Imposto il post come pubblicato              #
        #                                                       #
        #########################################################

        mysql.query(
            query="UPDATE autopostai_posts SET published = %s WHERE id = %s",
            parameters=(1, row['id'])
        )

    mysql.close()


if __name__ == "__main__":
    main()
