#!/bin/bash

redis-server --port 6379 &
redis-server --port 6389 &
redis-server --port 6399 &

doexit () {
    [[ -z "$(jobs -p)" ]] || kill $(jobs -p)
    [[ -z "$(jobs -p)" ]] || wait $(jobs -p)
    exit 0
}

trap doexit EXIT

for job in "$(jobs -p)"
do
    wait $job
done
