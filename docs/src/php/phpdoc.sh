#!/bin/bash

docker-compose run --rm phpdoc -d app -t docs/documentation/php --cache-folder=docs/.cache
