#!/bin/bash

#docker build -t autopostai .

docker run \
  --rm \
  --env-file .env \
  -v "$(pwd)/../.env":/app/.laravel-env \
  -v "$(pwd)/../storage":/app/storage \
  -v "$(pwd)":/app autopostai
