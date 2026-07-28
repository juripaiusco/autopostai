#!/bin/bash

# Crontab (adatta il path):
# *   *   *   *   *   /var/www/vhosts/.../dev_code/python/deploy/autopostai-python.sh >> /root/autopostai-python.log 2>&1
#
# Stile v1 (autopostai.sh): nessun docker-compose in produzione. Rebuild solo
# se Dockerfile o requirements.txt sono cambiati; il codice applicativo e'
# montato come volume, quindi un git pull che tocca solo il codice richiede
# solo un restart del container, non un rebuild dell'immagine.

(
    PATH_SCRIPT=$(dirname "$0")
    cd "$PATH_SCRIPT/.."

    echo ""
    echo "############################################################"
    echo "autopostai-python - START: $(date)"
    echo "------------------------------------------------------------"

    export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

    IMAGE_NAME="autopostai-python"
    CONTAINER_NAME="container_python"
    STATE_FILE=".docker_image_hash"

    current_hash() {
        cat Dockerfile requirements.txt | shasum -a 256 | awk '{print $1}'
    }

    needs_build() {
        if [ ! -f "$STATE_FILE" ]; then
            return 0
        fi
        [ "$(current_hash)" != "$(cat "$STATE_FILE")" ]
    }

    if needs_build; then
        echo "Dockerfile/requirements.txt cambiati: ricostruisco $IMAGE_NAME..."
        docker build -t "$IMAGE_NAME" .
        current_hash > "$STATE_FILE"

        if [ "$(docker ps -aq -f name=$CONTAINER_NAME)" ]; then
            echo "Arresto ed eliminazione del vecchio container $CONTAINER_NAME..."
            docker stop "$CONTAINER_NAME"
            docker rm "$CONTAINER_NAME"
        fi

        echo "Avvio del nuovo container $CONTAINER_NAME..."
        docker run -d \
            --name "$CONTAINER_NAME" \
            --restart always \
            -p 8000:8000 \
            --env-file .env \
            -v "$(pwd)":/app \
            "$IMAGE_NAME"
    else
        echo "L'immagine $IMAGE_NAME e' aggiornata."

        if [ -z "$(docker ps -q -f name=$CONTAINER_NAME)" ]; then
            echo "$CONTAINER_NAME non e' in esecuzione: lo avvio..."

            if [ "$(docker ps -aq -f name=$CONTAINER_NAME)" ]; then
                docker rm "$CONTAINER_NAME"
            fi

            docker run -d \
                --name "$CONTAINER_NAME" \
                --restart always \
                -p 8000:8000 \
                --env-file .env \
                -v "$(pwd)":/app \
                "$IMAGE_NAME"
        else
            echo "$CONTAINER_NAME gia' in esecuzione."
        fi
    fi

    echo "------------------------------------------------------------"
    echo "autopostai-python - END: $(date)"
)
