#!/bin/bash
# Worker di pubblicazione (publisher/) in produzione/beta: un giro per
# lancio, la cadenza la da' cron (stesso modello di v1 autopostai.sh).
#
# Crontab (un'istanza per riga, path della propria checkout):
#   * * * * * /var/www/vhosts/.../faper3/python/deploy/autopostai-publisher.sh >> /var/log/faper3-publisher.log 2>&1
#   * * * * * PUBLISHER_INSTANCE=beta PUBLISHER_IP=192.168.2.101 /var/www/vhosts/.../faper3-beta/python/deploy/autopostai-publisher.sh >> /var/log/faper3-publisher-beta.log 2>&1
#
# Variabili (tutte opzionali, default = produzione):
#   PUBLISHER_INSTANCE  nome istanza (lock, nome container)        default: prod
#   PUBLISHER_NETWORK   rete Docker condivisa tra le istanze        default: faper3_publisher
#   PUBLISHER_SUBNET    subnet della rete (diversa da v1: 192.168.1.0/24)  default: 192.168.2.0/24
#   PUBLISHER_IP        IP fisso del container: e' quello da autorizzare in
#                       MariaDB (GRANT ... TO 'utente'@'192.168.2.100')   default: 192.168.2.100
#   PUBLISHER_DB_HOST   come il container raggiunge MariaDB dell'host:
#                       il gateway della rete                     default: 192.168.2.1
#   PUBLISHER_DRY_RUN   1 = giro di prova senza chiamate ai provider (se non
#                       impostata vale quella in python/.env)
#
# Differenze rispetto al servizio immagini (autopostai-python.sh): qui nessun
# container fisso ne' porta esposta, solo `docker run --rm` di un giro.
# - DB_HOST forzato con -e: nella .env di Laravel c'e' l'host visto da PHP
#   (di solito 127.0.0.1), che dentro il container punterebbe al container.
# - storage montato in scrittura: Instagram genera li' le immagini quadrate.
# - durante un deploy creare <checkout>/.deploying: il giro viene saltato.

set -u

PY_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ROOT_DIR="$(cd "$PY_DIR/.." && pwd)"
LARAVEL_DIR="$ROOT_DIR/laravel"

INSTANCE="${PUBLISHER_INSTANCE:-prod}"
NETWORK="${PUBLISHER_NETWORK:-faper3_publisher}"
SUBNET="${PUBLISHER_SUBNET:-192.168.2.0/24}"
CONTAINER_IP="${PUBLISHER_IP:-192.168.2.100}"
DB_HOST_FOR_CONTAINER="${PUBLISHER_DB_HOST:-192.168.2.1}"

IMAGE_NAME="faper3-publisher"
STATE_FILE="$PY_DIR/.docker_publisher_hash"

export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

echo ""
echo "#### faper3-publisher [$INSTANCE] START $(date '+%Y-%m-%d %H:%M:%S')"

if [ -f "$ROOT_DIR/.deploying" ]; then
    echo "deploy in corso ($ROOT_DIR/.deploying): giro saltato"
    exit 0
fi

# Un solo giro alla volta per istanza, anche prima di arrivare al lock nel DB
# (il worker ne ha uno suo): evita container in coda se un giro dura > 1 min.
exec 9>"/tmp/faper3-publisher-$INSTANCE.lock"
if ! flock -n 9; then
    echo "giro precedente ancora in corso: esco"
    exit 0
fi

for f in "$LARAVEL_DIR/.env" "$PY_DIR/.env"; do
    if [ ! -f "$f" ]; then
        echo "manca $f: configurazione incompleta"
        exit 1
    fi
done

# Rebuild solo se cambiano Dockerfile o requirements.txt (il codice e' montato).
current_hash=$(cat "$PY_DIR/Dockerfile" "$PY_DIR/requirements.txt" | sha256sum | awk '{print $1}')
if [ -z "$(docker images -q "$IMAGE_NAME" 2>/dev/null)" ] || [ ! -f "$STATE_FILE" ] || [ "$current_hash" != "$(cat "$STATE_FILE")" ]; then
    echo "costruzione immagine $IMAGE_NAME..."
    docker build -t "$IMAGE_NAME" "$PY_DIR" || exit 1
    echo "$current_hash" > "$STATE_FILE"
fi

if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
    docker network create --subnet="$SUBNET" "$NETWORK" >/dev/null || exit 1
fi

docker run --rm \
    --name "faper3-publisher-$INSTANCE" \
    --network "$NETWORK" \
    --ip "$CONTAINER_IP" \
    --env-file "$PY_DIR/.env" \
    -e TZ=Europe/Rome \
    -e DB_HOST="$DB_HOST_FOR_CONTAINER" \
    ${PUBLISHER_DRY_RUN:+-e PUBLISHER_DRY_RUN="$PUBLISHER_DRY_RUN"} \
    -e LARAVEL_ENV_PATH=/laravel-env \
    -e STORAGE_PATH=/laravel-storage/app/public \
    -v "$PY_DIR:/app" \
    -v "$LARAVEL_DIR/.env:/laravel-env:ro" \
    -v "$LARAVEL_DIR/storage:/laravel-storage" \
    "$IMAGE_NAME" python -m publisher.worker
STATUS=$?

echo "#### faper3-publisher [$INSTANCE] END $(date '+%Y-%m-%d %H:%M:%S') (exit $STATUS)"
exit $STATUS
