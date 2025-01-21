#!/bin/bash

cd "$(dirname "$0")"

PROMPT=$1
IMAGE_NAME="stable_diffusion"
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
  echo "Costruzione dell'immagine Docker $IMAGE_NAME..."
  docker build -t $IMAGE_NAME .
  # Aggiorna il timestamp del file di stato
  touch .docker_image_built
else
  echo "L'immagine Docker $IMAGE_NAME è aggiornata."
fi

# Esegui il container
docker run \
  --rm \
  --env-file .env \
  -v "$(pwd)":/app $IMAGE_NAME \
  python \
  main.py \
  --prompt "$PROMPT"
