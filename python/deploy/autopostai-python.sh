#!/bin/bash
# Servizio immagini (FastAPI, main.py) in produzione/beta: container fisso con
# restart always. Lanciato da cron ogni minuto solo per (ri)costruire l'immagine
# se cambiano Dockerfile/requirements e riavviare il container se non gira
# (stile v1 autopostai.sh, nessun docker-compose in produzione).
#
# Crontab (un'istanza per riga, path della propria checkout):
#   * * * * * /var/www/vhosts/.../faper3/python/deploy/autopostai-python.sh >> /var/log/faper3-images.log 2>&1
#   * * * * * IMAGES_INSTANCE=beta IMAGES_PORT=8011 /var/www/vhosts/.../faper3-beta/python/deploy/autopostai-python.sh >> /var/log/faper3-images-beta.log 2>&1
#
# Variabili (opzionali, default = produzione):
#   IMAGES_INSTANCE  nome istanza (nome container)                  default: prod
#   IMAGES_PORT      porta sull'host, SOLO 127.0.0.1                default: 8010
#                    (8000 e' gia' usata dal servizio v1). Nella .env di
#                    Laravel: PYTHON_SERVICE_URL=http://127.0.0.1:<porta>
#
# Il servizio non ha autenticazione: la porta resta sull'interfaccia locale,
# mai esposta su internet. Il codice e' montato come volume: dopo un git pull
# che tocca python/ serve `docker restart faper3-images-<istanza>`.

set -u

PY_DIR="$(cd "$(dirname "$0")/.." && pwd)"
INSTANCE="${IMAGES_INSTANCE:-prod}"
PORT="${IMAGES_PORT:-8010}"
IMAGE_NAME="autopostai-python"
CONTAINER_NAME="faper3-images-$INSTANCE"
STATE_FILE="$PY_DIR/.docker_image_hash"

export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

echo ""
echo "#### faper3-images [$INSTANCE] START $(date '+%Y-%m-%d %H:%M:%S')"

if [ ! -f "$PY_DIR/.env" ]; then
    echo "manca $PY_DIR/.env: configurazione incompleta"
    exit 1
fi

start_container() {
    docker run -d \
        --name "$CONTAINER_NAME" \
        --restart always \
        -p "127.0.0.1:$PORT:8000" \
        --env-file "$PY_DIR/.env" \
        -v "$PY_DIR:/app" \
        "$IMAGE_NAME" >/dev/null && echo "$CONTAINER_NAME avviato su 127.0.0.1:$PORT"
}

current_hash=$(cat "$PY_DIR/Dockerfile" "$PY_DIR/requirements.txt" | sha256sum | awk '{print $1}')
if [ -z "$(docker images -q "$IMAGE_NAME" 2>/dev/null)" ] || [ ! -f "$STATE_FILE" ] || [ "$current_hash" != "$(cat "$STATE_FILE")" ]; then
    echo "Dockerfile/requirements.txt cambiati: ricostruisco $IMAGE_NAME..."
    docker build -t "$IMAGE_NAME" "$PY_DIR" || exit 1
    echo "$current_hash" > "$STATE_FILE"
    docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1
    start_container
elif [ -z "$(docker ps -q -f "name=^${CONTAINER_NAME}\$")" ]; then
    echo "$CONTAINER_NAME non in esecuzione: lo avvio"
    docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1
    start_container
else
    echo "$CONTAINER_NAME gia' in esecuzione"
fi

echo "#### faper3-images [$INSTANCE] END $(date '+%Y-%m-%d %H:%M:%S')"
