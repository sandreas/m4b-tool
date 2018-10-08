#!/bin/bash

for DIRECTORY in "data/input data/output"; do
    if [ ! -d "$DIRECTORY" ]; then
        mkdir -p "$DIRECTORY"
    fi
done

PATH_MAPPING="$(pwd)/data:/mnt"
docker run -it -w "/mnt" -v "$PATH_MAPPING" --name=m4b-tool m4b-tool
