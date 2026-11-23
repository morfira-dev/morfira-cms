#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 1 ]; then
    printf 'Usage: %s /absolute/backup-directory\n' "$0" >&2
    exit 64
fi

backup_directory=$1
case "$backup_directory" in
    /*) ;;
    *) printf 'Backup directory must be an absolute path.\n' >&2; exit 64 ;;
esac

umask 077
mkdir -p "$backup_directory"
staging=$(mktemp -d "$backup_directory/.backup.XXXXXX")
cleanup() {
    rm -f -- "$staging/database.sql.gz" "$staging/media.tar.gz" "$staging/private-media.tar.gz" "$staging/manifest.txt"
    rmdir -- "$staging"
}
trap cleanup EXIT
docker compose -f compose.prod.yaml exec -T database sh -c 'mariadb-dump --single-transaction --quick -ucms -p"$MARIADB_PASSWORD" cms' | gzip -9 > "$staging/database.sql.gz"
docker compose -f compose.prod.yaml exec -T app tar -C /app/back/public -czf - media > "$staging/media.tar.gz"
docker compose -f compose.prod.yaml exec -T app tar -C /app/back/var -czf - private-media > "$staging/private-media.tar.gz"
date -u '+created_at=%Y-%m-%dT%H:%M:%SZ' > "$staging/manifest.txt"
git rev-parse HEAD >> "$staging/manifest.txt"

for file in database.sql.gz media.tar.gz private-media.tar.gz manifest.txt; do
    mv "$staging/$file" "$backup_directory/$file"
done

printf 'Backup created in %s\n' "$backup_directory"
