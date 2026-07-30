"""Configurazione centralizzata del worker di pubblicazione.

Le credenziali DB e i valori d'ambiente arrivano dalla `.env` di Laravel (montata
come `.laravel-env`, esattamente come faceva v1) con fallback all'ambiente del
container. Tutti i valori "magici" di v1 (base URL dei provider, cap immagini,
backoff, path storage, timezone) sono raccolti qui invece di essere sparsi e
hardcoded nel codice.
"""

import os

import pytz
from dotenv import load_dotenv

# La `.env` di Laravel e' la fonte di verita' per DB e APP_URL: montandola qui
# evitiamo di duplicare le credenziali. Non solleva se il file non c'e' (in test
# si usano direttamente le variabili d'ambiente).
load_dotenv(dotenv_path=os.getenv("LARAVEL_ENV_PATH", ".laravel-env"))


def _env(key: str, default: str | None = None) -> str | None:
    value = os.getenv(key)
    return value if value not in (None, "") else default


def _bool(key: str, default: bool = False) -> bool:
    raw = os.getenv(key)
    if raw is None:
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


# --- Fuso orario -----------------------------------------------------------
LOCAL_TIMEZONE = pytz.timezone(_env("APP_TIMEZONE", "Europe/Rome"))

# --- Database (stesso MariaDB di Laravel, letto direct) --------------------
DB_HOST = _env("DB_HOST", "db")
DB_PORT = int(_env("DB_PORT", "3306"))
DB_DATABASE = _env("DB_DATABASE", "laravel")
DB_USERNAME = _env("DB_USERNAME", "root")
DB_PASSWORD = _env("DB_PASSWORD", "secret")
# Prefisso tabelle Laravel: viene interpolato nei nomi tabella (non e' input
# utente, arriva dalla nostra stessa .env, quindi e' sicuro concatenarlo).
DB_PREFIX = _env("DB_PREFIX", "")

# --- App / storage ---------------------------------------------------------
APP_URL = _env("APP_URL", "http://localhost")
# Radice del disco `public` di Laravel dove vivono le immagini dei post
# (posts/{id}/{file}). Montata nel container del publisher.
STORAGE_PATH = _env("STORAGE_PATH", "/var/www/html/laravel/storage/app/public")

# --- Provider esterni (base URL, con default sensati) ----------------------
OPENAI_MODEL = _env("OPENAI_MODEL", "gpt-4o-mini")
OPENAI_BASE_URL = _env("OPENAI_API_URL")  # None => default SDK ufficiale
META_API_BASE_URL = _env("META_API_BASE_URL", "https://graph.facebook.com/v21.0")
# Token Meta GLOBALE (non per-account): un'unica app Meta gestita dall'admin,
# condivisa alle pagine via Business Manager. Da qui si ricava il page access
# token per ogni meta_page_id. Vive in python/.env (gitignored), come v1.
META_USER_ACCESS_TOKEN = _env("META_USER_ACCESS_TOKEN")
LINKEDIN_BASE_URL = _env("LINKEDIN_BASE_URL", "https://api.linkedin.com/v2")
MAILCHIMP_BASE_URL = _env("MAILCHIMP_BASE_URL", "https://[DATACENTER].api.mailchimp.com/3.0")
BREVO_BASE_URL = _env("BREVO_BASE_URL", "https://api.brevo.com/v3")

# --- Tunable (ex magic-number sparsi in v1) --------------------------------
LINKEDIN_MAX_IMAGES = 9
INSTAGRAM_MAX_IMAGES = 10
# Oltre questa finestra dalla pubblicazione, un post smette di essere monitorato
# per nuovi commenti anche se il cap non e' stato raggiunto (safety valve, v1).
TASK_COMPLETE_MAX_WAIT_DAYS = 14
# Backoff esponenziale del polling commenti: 2^tentativi minuti, fino a 1 giorno.
TASK_COMPLETE_MAX_BACKOFF_MINUTES = 24 * 60
# Batch scansionato da reply_send per trovare il primo commento idoneo
# (canale con auto_reply_enabled attivo) senza restare bloccati in testa alla coda.
REPLY_SEND_BATCH_SIZE = 20
# Newsletter smtp_custom: throttling 20 contatti/5 minuti per account, spalmato
# sul tick da 1 minuto del worker (20/5 = 4 per tick, publisher/tasks/newsletter_send.py).
NEWSLETTER_SMTP_BATCH_SIZE = 4

# --- Modalita' -------------------------------------------------------------
# DRY_RUN: esegue tutta l'orchestrazione (query, parsing channels, scrittura DB,
# accodamento notifica) SENZA chiamare i provider esterni ne' il LLM. Ogni canale
# restituisce un id/url simulato. Serve a validare il flusso in dev con chiavi finte.
DRY_RUN = _bool("PUBLISHER_DRY_RUN", False)
DEBUG = _bool("PUBLISHER_DEBUG", False)


def table(name: str) -> str:
    """Nome tabella con prefisso Laravel applicato."""
    return f"{DB_PREFIX}{name}"
