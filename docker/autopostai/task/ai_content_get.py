import config as cfg
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.wordpress import WordPressPost
from task.ai_generate import ai_generate

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
