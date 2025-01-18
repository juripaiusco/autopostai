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
