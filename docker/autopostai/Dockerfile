#FROM python:3.13.1-slim
FROM python:3.9.20-slim

# Installa librerie
RUN pip install \
    mysql-connector-python \
    openai \
    load_dotenv \
    requests \
    pytz \
    tqdm \
    tiktoken \
    markdown \
    pillow \
    fastapi \
    uvicorn \
    brevo

# Imposta la directory di lavoro
WORKDIR /app
