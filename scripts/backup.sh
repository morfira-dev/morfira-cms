#!/bin/sh
set -eu

if [ "$#" -ne 1 ]; then
    printf 'Usage: %s /absolute/backup-directory\n' "$0" >&2
    exit 64
fi

backup_directory=$1
case "$backup_directory" in
    /*) ;;
    *) printf 'Backup directory must be an absolute path.\n' >&2; exit 64 ;;
esac

mkdir -p "$backup_directory"
docker compose -f compose.prod.yaml exec -T database sh -c 'mariadb-dump --single-transaction --quick -ucms -p"$MARIADB_PASSWORD" cms' | gzip -9 > "$backup_directory/database.sql.gz"
docker compose -f compose.prod.yaml exec -T app tar -C /app/back/public -czf - media > "$backup_directory/media.tar.gz"
date -u '+created_at=%Y-%m-%dT%H:%M:%SZ' > "$backup_directory/manifest.txt"
git rev-parse HEAD >> "$backup_directory/manifest.txt"

printf 'Backup created in %s\n' "$backup_directory"
