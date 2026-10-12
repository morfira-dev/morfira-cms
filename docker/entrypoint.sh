#!/bin/sh
set -eu

php bin/cms doctrine:migrations:migrate --no-interaction

exec "$@"
