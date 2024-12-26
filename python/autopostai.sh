#!/bin/bash

#docker build -t autopostai .

docker network create \
  --subnet=192.168.1.0/24 \
  autopostai_network

docker run \
  --rm \
  --net autopostai_network \
  --ip 192.168.1.100 \
  --env-file .env \
  -v "$(pwd)/../.env":/app/.laravel-env \
  -v "$(pwd)/../storage":/app/storage \
  -v "$(pwd)":/app autopostai

docker network rm \
  autopostai_network
