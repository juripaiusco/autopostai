#!/bin/bash

#docker build -t ai2post .

docker run \
  --rm \
  --env-file .env \
  -v "$(pwd)":/app ai2post
