import json
import config as cfg
from decimal import Decimal
from services.mysql import Mysql
from datetime import datetime, timedelta


def task_complete(debug = False):

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Task complete CTRL - START ------------")

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Database query")

    time_now = datetime.now(cfg.LOCAL_TIMEZONE)
    mysql = Mysql()
    mysql.connect()

    rows = mysql.query(f"""
            SELECT  {cfg.DB_PREFIX}posts.id AS id,
                    {cfg.DB_PREFIX}posts.user_id AS user_id,
                    {cfg.DB_PREFIX}posts.check_attempts AS check_attempts,
                    {cfg.DB_PREFIX}posts.channels AS channels,
                    SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'facebook' THEN 1 ELSE 0 END) AS facebook_comments_count,
                    SUM(CASE WHEN {cfg.DB_PREFIX}comments.channel = 'instagram' THEN 1 ELSE 0 END) AS instagram_comments_count,
                    {cfg.DB_PREFIX}posts.on_hold_until AS on_hold_until,
                    {cfg.DB_PREFIX}posts.created_at AS created_at

                FROM {cfg.DB_PREFIX}posts
                    LEFT JOIN {cfg.DB_PREFIX}comments ON {cfg.DB_PREFIX}posts.id = {cfg.DB_PREFIX}comments.post_id

            WHERE {cfg.DB_PREFIX}posts.published = 1
                AND {cfg.DB_PREFIX}posts.task_complete = 0
                AND ({cfg.DB_PREFIX}posts.on_hold_until IS NULL OR {cfg.DB_PREFIX}posts.on_hold_until <= '{time_now}')
        """)

    if rows[0]['id'] is not None:

        if debug:
            print(" " * 19, "---")
            print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Post CTRL ID:", rows[0]['id'])

        for row in rows:

            channels = json.loads(row['channels'])

            # Imposto e verifico il task_complete per ogni canali
            for i in channels:
                channels[i]['task_complete'] = 0

                if channels[i]['on'] == '0':
                    channels[i]['task_complete'] = 1

                if channels[i]['reply_on'] == '0':
                    channels[i]['task_complete'] = 1

                if channels[i]['reply_n'] == '0' or channels[i]['reply_n'] is None:
                    channels[i]['task_complete'] = 1

            # Verifico che i commenti abbiano avuto risposta e verifico che
            # il numero di risposte sia stato sufficiente alle richieste
            # dell'utente.
            for i in channels:
                if (channels[i]['name'] == 'Facebook'
                    and Decimal(row['facebook_comments_count'] or 0) >= Decimal(channels[i]['reply_n'] or 0)):
                    channels[i]['task_complete'] = 1

                if (channels[i]['name'] == 'Instagram'
                    and Decimal(row['instagram_comments_count'] or 0) >= Decimal(channels[i]['reply_n'] or 0)):
                    channels[i]['task_complete'] = 1

            # Semplice print per mostrare i task_complete per singolo canale riferito al post
            if debug is True:
                for i in channels:
                    print(" " * 19, channels[i]['name'], "task complete:", channels[i]['task_complete'])

            # Verifico che tutti i canali siano task_complete così da rendere il post task_complete
            # se solo un canale non è completo il task_complete va a 0
            task_complete = 1
            for i in channels:
                if channels[i]['task_complete'] == 0:
                    task_complete = 0

            # Verifico che sia trascorsa una settimana, in caso marchio il post come task_complete
            if debug is True:
                print("\n", " " * 19, "Waiting days:", (row['on_hold_until'] - row['created_at']).days)

            if (row['on_hold_until'] - row['created_at']).days >= 14:
                task_complete = 1

            # Se il task non è ancora completo, imposto un orario per la
            # prossima verifica dei commenti
            if task_complete == 0:
                # Calcolo il tempo per il prossimo controllo dei commenti
                next_wait = calculate_next_hold_time(row['check_attempts'])
                mysql.query(
                    query=f"UPDATE {cfg.DB_PREFIX}posts SET on_hold_until = %s, check_attempts = check_attempts + 1 WHERE id = %s",
                    parameters=(time_now + next_wait, row['id'])
                )

            # Imposto il post come task_complete
            if task_complete == 1:
                mysql.query(
                    query=f"UPDATE {cfg.DB_PREFIX}posts SET task_complete = %s WHERE id = %s",
                    parameters=(task_complete, row['id'])
                )

            if debug:
                print(
                    datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'),
                    "Post ID:",
                    rows[0]['id'],
                    "- task_complete:",
                    task_complete
                )
                print(" " * 19, "---")

    mysql.close()

    if debug:
        print(datetime.now(cfg.LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S'), "Task complete CTRL - END --------------")

def calculate_next_hold_time(check_attempts):
    # Incremento esponenziale con un limite massimo di 1 settimana
    max_wait = timedelta(weeks=1)
    next_wait = min(timedelta(minutes=2 ** check_attempts), max_wait)
    return next_wait
