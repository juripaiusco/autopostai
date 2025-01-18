import config as cfg
from services.gpt import GPT
from datetime import datetime
from services.mysql import Mysql

# In questa funziona mi collego ad OpenAI e recupero l'output
def openai_generate(data, prompt, img_path = None, type = None, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Verifico quanti token sono stati utilizzati, per non superare la soglia
    # dei token disponibili per utente
    rows = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}users.id AS id,
                    {cfg.DB_PREFIX}users.tokens_limit AS tokens_limit,
                    COALESCE(SUM({cfg.DB_PREFIX}token_logs.tokens_used), 0) as tokens_used_total

                FROM {cfg.DB_PREFIX}users
                    LEFT JOIN {cfg.DB_PREFIX}token_logs
                        ON {cfg.DB_PREFIX}users.id = {cfg.DB_PREFIX}token_logs.user_id

            WHERE {cfg.DB_PREFIX}users.id = {data['user_id']}

            GROUP BY
                {cfg.DB_PREFIX}users.id
        """)

    # Nel caso in cui i token sono stati superati, il post viene marchiato come task_complete
    # così non verrà più usato nei prossimi controlli
    if rows[0]['tokens_used_total'] >= rows[0]['tokens_limit']\
        and type == 'post':
        if debug is True:
            print(
                datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                "User ID: ",
                data['user_id'],
                "Token limit exceeded:",
                f"{rows[0]['tokens_used_total']} / {rows[0]['tokens_limit']}"
            )
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET published = %s, task_complete = %s WHERE id = %s",
            parameters=(1, 1, data['id'])
        )

        return None

    # ---------------------------------------------------------
    # Classe OpenAI
    gpt = GPT(api_key=data['openai_api_key'])

    # Verifico se l'immagine è da inviare all'AI e se l'immagine esiste
    if img_path is not None:
        contenuto, tokens_used = gpt.generate(prompt, img_path)
    else:
        contenuto, tokens_used = gpt.generate(prompt)

    # ---------------------------------------------------------
    # Salvo i token utilizzati per generare il contenuto
    # i token vegono salvati nella tabella token_logs, in questo
    # modo si riesce a tenere traccia delle interazione con OpenAI
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
        type,
        data['id'],
        tokens_used,
        cfg.CURRENT_TIME
    ))

    mysql.close()

    return contenuto
