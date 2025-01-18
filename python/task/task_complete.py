from services.mysql import Mysql
import config as cfg
from datetime import datetime, timedelta

def task_complete(debug = False):
    time_now = datetime.now(cfg.LOCAL_TIMEZONE)
    mysql = Mysql()
    mysql.connect()

    rows = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}posts.id AS id,
                    {cfg.DB_PREFIX}posts.user_id AS user_id,
                    {cfg.DB_PREFIX}posts.check_attempts AS check_attempts,
                    {cfg.DB_PREFIX}posts.channels AS channels,
                    SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'facebook' THEN 1 ELSE 0 END) AS facebook_comments_count,
                    SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count

                FROM {cfg.DB_PREFIX}posts
                    LEFT JOIN {cfg.DB_PREFIX}comments ON {cfg.DB_PREFIX}posts.id = {cfg.DB_PREFIX}comments.post_id

            WHERE {cfg.DB_PREFIX}posts.published = 1
                AND {cfg.DB_PREFIX}posts.task_complete = 0
                AND ({cfg.DB_PREFIX}posts.on_hold_until IS NULL OR {cfg.DB_PREFIX}posts.on_hold_until <= '{time_now}')
        """)

    if rows[0]['id'] is not None:
        for row in rows:

            # Calcolo il tempo per il prossimo controllo dei commenti
            next_wait = calculate_next_hold_time(row['check_attempts'])
            mysql.query(
                query=f"UPDATE {cfg.DB_PREFIX}posts SET on_hold_until = %s, check_attempts = check_attempts + 1 WHERE id = %s",
                parameters=(time_now + next_wait, row['id'])
            )

            # Verifico che i commenti abbiano avuto risposta e verifico che
            # il numero di risposte sia stato sufficiente alle richieste
            # dell'utente.


            # Verifico che sia trascorsa una settimana, in caso marchio il
            # post come task_complete


    mysql.close()

    return 0

def calculate_next_hold_time(check_attempts):
    # Incremento esponenziale con un limite massimo di 1 settimana
    max_wait = timedelta(weeks=1)
    next_wait = min(timedelta(minutes=2 ** check_attempts), max_wait)
    return next_wait
