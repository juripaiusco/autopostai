"""Worker di pubblicazione automatica dei post (porting ottimizzato della v1).

Componente separato dal servizio FastAPI di generazione immagini (`main.py` +
`providers/`): qui vive la logica cron one-shot che, ogni minuto, pubblica i post
schedulati sui vari canali e accoda la notifica "post inviato".

Entry point: `python -m publisher.worker`.
"""
