import os
import pytz
from dotenv import load_dotenv
from datetime import datetime, timedelta

# Importo le variabili di Laravel per non avere env duplicati
load_dotenv(dotenv_path=".laravel-env")

# Fuso orario locale (ad esempio, Europa/Roma)
LOCAL_TIMEZONE = pytz.timezone('Europe/Rome')

# Ottieni la data attuale
CURRENT_TIME = datetime.now(LOCAL_TIMEZONE).strftime('%Y-%m-%d %H:%M:%S')

# Prefisso del database
DB_PREFIX = os.getenv('DB_PREFIX')

URL = os.getenv('APP_URL')
