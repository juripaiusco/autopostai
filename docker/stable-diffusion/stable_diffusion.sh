#!/bin/bash

(
    cd "$(dirname "$0")"

    # Carica il file .env e rende le variabili disponibili
    set -a
    source .env
    set +a

    PROMPT=$1
    IMAGE_NAME=$2
    DOCKER_IMAGE_NAME="stable_diffusion"
    DOCKERFILE="Dockerfile"

    # Funzione per controllare se l'immagine è aggiornata
    needs_build() {
      if [ ! -f ".docker_image_built" ]; then
        # Primo build
        return 0
      fi

      # Controlla la data di modifica del Dockerfile rispetto al file di stato
      if [ "$DOCKERFILE" -nt ".docker_image_built" ]; then
        return 0
      fi

      return 1
    }

    # Compila l'immagine solo se necessario
    if needs_build; then
      # echo "Costruzione dell'immagine Docker $DOCKER_IMAGE_NAME..."
      docker build -t $DOCKER_IMAGE_NAME . > /dev/null 2>&1
      # Aggiorna il timestamp del file di stato
      touch .docker_image_built
    fi

    # Esegui il container
    docker run \
      --rm \
      --env-file .env \
      -v $DOCKER_DIR:/app $DOCKER_IMAGE_NAME \
      python \
      main.py \
      --prompt "$PROMPT" \
      --image_name "$IMAGE_NAME"
)
