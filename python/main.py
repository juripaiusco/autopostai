import os
from dotenv import load_dotenv
from lib.gpt import GPT
from lib.meta import Meta
from lib.mysql import Mysql

load_dotenv(dotenv_path=".laravel-env")

def main():

    # Recupero i dati dal database per generare i Post
    mysql = Mysql()
    mysql.connect()
    rows = mysql.query("""
            SELECT  autopostai_posts.id AS id,
                    autopostai_posts.user_id AS user_id,
                    autopostai_posts.ai_prompt_post AS prompt,
                    autopostai_posts.img AS img,
                    autopostai_posts.img_ai_check_on AS img_ai_check_on,
                    autopostai_posts.meta_facebook_on AS meta_facebook,
                    autopostai_posts.meta_instagram_on AS meta_instagram,
                    autopostai_posts.wordpress_on AS wordpress,
                    autopostai_posts.newsletter_on AS newsletter,
                    autopostai_posts.published_at AS published_at,
                    autopostai_posts.published AS published,
                    autopostai_settings.ai_personality AS ai_personality,
                    autopostai_settings.ai_prompt_prefix AS ai_prompt_prefix,
                    autopostai_settings.openai_api_key AS openai_api_key,
                    autopostai_settings.meta_page_id AS meta_page_id

                FROM autopostai_posts
                INNER JOIN autopostai_settings ON autopostai_settings.user_id = autopostai_posts.user_id

            WHERE autopostai_posts.published IS NULL
        """)
    mysql.close()

    # Leggo tutti i post
    for row in rows:

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

        # Classe OpenAI
        gpt = GPT(api_key=row['openai_api_key'])

        # Classe Meta
        meta = Meta(page_id=row['meta_page_id'])

        # Verifico se l'immagine è da inviare all'AI e se l'immagine esiste
        if row['img_ai_check_on'] and row['img']:
            contenuto = gpt.generate(prompt, img_path)
        else:
            contenuto = gpt.generate(prompt)

        # Verifico se l'immagine è stata caricata e la invio ai canali scelti
        if row['img']:

            if row['meta_facebook_on']:
                meta.fb_generate_post(contenuto, img_path)

            if row['meta_instagram_on']:
                meta.ig_generate_post(contenuto, img_url)

        else:

            if row['meta_facebook_on']:
                meta.fb_generate_post(contenuto)


if __name__ == "__main__":
    main()
