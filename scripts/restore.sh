#!/bin/sh
set -eu

if [ "$#" -ne 2 ] || [ "$1" != "--confirm-destructive-restore" ]; then
    printf 'Usage: %s --confirm-destructive-restore /absolute/backup-directory\n' "$0" >&2
    exit 64
fi

backup_directory=$2
case "$backup_directory" in
    /*) ;;
    *) printf 'Backup directory must be an absolute path.\n' >&2; exit 64 ;;
esac

test -f "$backup_directory/database.sql.gz"
test -f "$backup_directory/media.tar.gz"

docker compose -f compose.prod.yaml stop app
gzip -dc "$backup_directory/database.sql.gz" | docker compose -f compose.prod.yaml exec -T database sh -c 'mariadb -ucms -p"$MARIADB_PASSWORD" cms'
docker compose -f compose.prod.yaml run --rm --no-deps app sh -c 'find /app/back/public/media -mindepth 1 -delete'
docker compose -f compose.prod.yaml run --rm --no-deps -T app tar -C /app/back/public -xzf - < "$backup_directory/media.tar.gz"
docker compose -f compose.prod.yaml up -d app

printf 'Restore completed from %s\n' "$backup_directory"
