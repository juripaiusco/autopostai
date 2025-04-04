import json
import config as cfg
from services.mysql import Mysql
from task.posts.base import BasePost
from task.posts.facebook import FacebookPost
from task.posts.instagram import InstagramPost
from task.posts.newsletter import NewsletterPost
from task.posts.wordpress import WordPressPost
from task.ai_generate import ai_generate
import re

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
        return parse_shortcodes(row[0]['ai_content'], data=data)

    base_post = BasePost(data=data)
    prompt = base_post.prompt_get()

    if channelName == 'WordPress':
        wordpress_post = WordPressPost(data=data)
        prompt = wordpress_post.prompt_get()

    if channelName == 'Newsletter':
        newsletter_post = NewsletterPost(data=data)
        prompt = newsletter_post.prompt_get()

    # Mi connetto al LLM
    content = parse_shortcodes(ai_generate(
        data=data,
        prompt=prompt,
        img_path=base_post.img_path_get() if data['img_ai_check_on'] == '1' else None,
        type="post",
        debug=debug
    ), data=data)

    # Salvo il contenuto generato dall'AI
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
        parameters=(content, data['id'])
    )

    mysql.close()

    return content


def get_post_url(post_id, channel, data_type, data):
    mysql = Mysql()
    mysql.connect()

    row = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}posts.user_id AS user_id,
                    {cfg.DB_PREFIX}posts.channels AS channels
                FROM {cfg.DB_PREFIX}posts
            WHERE {cfg.DB_PREFIX}posts.id = {post_id}
        """)

    # Verifico che il post sia stato pubblicato dallo stesso utente
    # che vuole usare lo shortcode, in modo da non pubblicare post
    # di altri utenti sui canali di altri
    if row[0].get('user_id') != data.get('user_id'):
        return "[URL non disponibile]"

    if row[0].get('user_id') == data.get('user_id'):
        if row[0]['channels'] is not None:
            channels = json.loads(row[0]['channels'])

            for i in channels:
                if (channels[i]['name'] == channel
                    and channels[i]['on'] == '1'
                    and channels[i]['id'] is not None):
                    return channels[i].get(data_type, "[URL non ancora disponibile]")

    mysql.close()


def parse_shortcodes(text, data=None):
    pattern = re.compile(r"\[(\w+)\s+(\w+)\s+id=(\d+)\]")

    def replace_match(match):
        channel, data_type, post_id = match.groups()
        post_id = int(post_id)

        if data_type.lower() == "url":
            return get_post_url(post_id, channel, data_type, data)

        return match.group(0)  # Restituisce lo shortcode originale se non è valido

    return pattern.sub(replace_match, text)
