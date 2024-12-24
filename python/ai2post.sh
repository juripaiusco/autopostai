#!/bin/bash

#docker build -t ai2post .

docker run \
  --rm \
  --env-file .env \
  -v "$(pwd)/../.env":/app/.laravel-env \
  -v "$(pwd)":/app ai2post
