#FROM python:3.13.1-slim
FROM python:3.9.20-slim

# Installa librerie
RUN pip install \
    mysql-connector-python \
    openai \
    load_dotenv \
    requests \
    pytz

# Imposta la directory di lavoro
WORKDIR /app

# Comando di default per eseguire Python
CMD ["python", "main.py"]
