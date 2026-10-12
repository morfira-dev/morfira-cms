#!/bin/sh
set -eu

git pull --ff-only
docker compose -f compose.prod.yaml build --pull app
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec -T app php bin/cms doctrine:migrations:status
