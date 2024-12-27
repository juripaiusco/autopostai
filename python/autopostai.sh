#!/bin/bash

IMAGE_NAME="autopostai"
NETWORK_NAME="autopostai_network"
SUBNET="192.168.1.0/24"
CONTAINER_IP="192.168.1.100"
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

# Crea la rete Docker, se non esiste
if ! docker network ls | grep -q $NETWORK_NAME; then
  docker network create --subnet=$SUBNET $NETWORK_NAME
fi

# Esegui il container
docker run \
  --rm \
  --net $NETWORK_NAME \
  --ip $CONTAINER_IP \
  --env-file .env \
  -v "$(pwd)/../.env":/app/.laravel-env \
  -v "$(pwd)/../storage":/app/storage \
  -v "$(pwd)":/app $IMAGE_NAME

# Rimuovi la rete Docker dopo l'esecuzione
docker network rm $NETWORK_NAME
