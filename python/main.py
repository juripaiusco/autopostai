import os
from dotenv import load_dotenv
from lib.gpt import GPT
from lib.meta import Meta
from lib.mysql import Mysql

load_dotenv(dotenv_path=".laravel-env")

def main():
    # prompt = "Crea un testo di prova super semplice e super breve"
    # gpt = GPT()
    # contenuto = gpt.generate(prompt)
    # meta = Meta()
    # meta.fb_generate_post(contenuto)

    mysql = Mysql()
    mysql.connect()
    rows = mysql.query("""
            SELECT  autopostai_posts.id AS id,
                    autopostai_posts.user_id AS user_id,
                    autopostai_posts.prompt AS prompt,
                    autopostai_posts.img AS img,
                    autopostai_posts.meta_facebook AS meta_facebook,
                    autopostai_posts.meta_instagram AS meta_instagram,
                    autopostai_posts.wordpress AS wordpress,
                    autopostai_posts.newsletter AS newsletter,
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

    for row in rows:
        prompt = ""

        if row['ai_personality']:
            prompt = prompt + f"{row['ai_personality']}\n\n"

        if row['ai_prompt_prefix']:
            prompt = prompt + f"{row['ai_prompt_prefix']}\n\n"

        if row['prompt']:
            prompt = prompt + "#Guidelines\n\n"
            prompt = prompt + f"{row['prompt']}"

        img_url = f"{os.getenv('APP_URL')}/storage/posts/{row['id']}/{row['img']}"

        # gpt = GPT()
        # contenuto = gpt.generate(prompt, img_path)

        # meta = Meta()
        # meta.fb_generate_post(contenuto, img_path)

        # meta = Meta()
        # meta.ig_generate_post(contenuto, "https://.../bracciale.jpg")


if __name__ == "__main__":
    main()
