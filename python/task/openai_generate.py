import config as cfg
from services.gpt import GPT
from services.mysql import Mysql

# In questa funziona mi collego ad OpenAI e recupero l'output
def openai_generate(data, prompt, img_path = None, type = None):
    # Classe OpenAI
    gpt = GPT(api_key=data['openai_api_key'])

    # Verifico se l'immagine è da inviare all'AI e se l'immagine esiste
    if img_path is not None:
        contenuto, tokens_used = gpt.generate(prompt, img_path)
    else:
        contenuto, tokens_used = gpt.generate(prompt)

    # ---------------------------------------------------------
    mysql = Mysql()
    mysql.connect()

    # Salvo il contenuto generato dall'AI
    mysql.query(
        query=f"UPDATE {cfg.DB_PREFIX}posts SET ai_content = %s WHERE id = %s",
        parameters=(contenuto, data['id'])
    )

    # Salvo i token utilizzati per questo post
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
