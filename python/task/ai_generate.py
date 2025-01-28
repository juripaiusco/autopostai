import config as cfg
from services.gpt import GPT
from services.mysql import Mysql
from task.task_complete import token_limit_exceeded

# In questa funziona mi collego al LLM e recupero l'output dopo aver inviato il prompt
def ai_generate(data, prompt, img_path = None, type = None, debug = False):
    mysql = Mysql()
    mysql.connect()

    # Nel caso in cui i token sono stati superati, il post viene marchiato come task_complete
    # così non verrà più usato nei prossimi controlli
    if token_limit_exceeded(user_id=data['user_id'], debug=debug) == True and type == 'post':
        mysql.query(
            query=f"UPDATE {cfg.DB_PREFIX}posts SET published = %s, task_complete = %s WHERE id = %s",
            parameters=(1, 1, data['id'])
        )

        return None

    # ---------------------------------------------------------
    # OpenAI
    gpt = GPT(api_key=data['openai_api_key'])
    contenuto, tokens_used = gpt.generate(prompt, img_path)

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
