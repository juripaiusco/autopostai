import json
import config as cfg
from services.mysql import Mysql
from task.ai_content_get import ai_content_get
from fastapi import FastAPI, Request
from pydantic import BaseModel


app = FastAPI()

# Definisci il modello dei dati attesi
class GenerateRequest(BaseModel):
    id: int

@app.post("/generate")
async def generate(request_data: GenerateRequest):
    id = request_data.id

    mysql = Mysql()
    mysql.connect()

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
                    {cfg.DB_PREFIX}settings.wordpress_password AS wordpress_password,
                    {cfg.DB_PREFIX}settings.wordpress_cat_id AS wordpress_cat_id,
                    {cfg.DB_PREFIX}settings.mailchimp_api AS mailchimp_api,
                    {cfg.DB_PREFIX}settings.mailchimp_datacenter AS mailchimp_datacenter,
                    {cfg.DB_PREFIX}settings.mailchimp_list_id AS mailchimp_list_id,
                    {cfg.DB_PREFIX}settings.mailchimp_from_name AS mailchimp_from_name,
                    {cfg.DB_PREFIX}settings.mailchimp_from_email AS mailchimp_from_email,
                    {cfg.DB_PREFIX}settings.mailchimp_template AS mailchimp_template,
                    {cfg.DB_PREFIX}settings.mailchimp_template_cta AS mailchimp_template_cta

                FROM {cfg.DB_PREFIX}posts
                    INNER JOIN {cfg.DB_PREFIX}settings
                        ON {cfg.DB_PREFIX}settings.user_id = {cfg.DB_PREFIX}posts.user_id

            WHERE {cfg.DB_PREFIX}posts.id = {id}
        """)

    for row in rows:

        channels = json.loads(row['channels'])

        for i in channels:
            if (channels[i]['name'] == 'Facebook'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                ai_content = ai_content_get(
                    channelName=channels[i]['name'],
                    data=row
                )

            if (channels[i]['name'] == 'Instagram'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                ai_content = ai_content_get(
                    channelName=channels[i]['name'],
                    data=row
                )

            if (channels[i]['name'] == 'WordPress'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                ai_content = ai_content_get(
                    channelName=channels[i]['name'],
                    data=row
                )

            if (channels[i]['name'] == 'Newsletter'
                and channels[i]['on'] == '1'
                and channels[i]['id'] is None):
                ai_content = ai_content_get(
                    channelName=channels[i]['name'],
                    data=row
                )

    mysql.close()

    return {"status": "success", "content": f"{ai_content}"}
