#!/bin/bash

SCRIPTPATH=$(dirname "$0")"/";

cd ${SCRIPTPATH};

. ${SCRIPTPATH}"_config.sh"

if ! pgrep -f "queue:work" > /dev/null
then
    echo "Starting queue worker..."
    $EXE_PHP artisan queue:work --sleep=3 --tries=3 --timeout=90 &
else
    echo "Worker already running."
fi
