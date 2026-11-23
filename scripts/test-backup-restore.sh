#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
fixture=$(mktemp -d "${TMPDIR:-/tmp}/morfira-archive-test.XXXXXX")
trap 'rm -rf -- "$fixture"' EXIT
mkdir -p "$fixture/bin" "$fixture/restore/media" "$fixture/restore/private-media"

export ARCHIVE_TEST_CALLS="$fixture/calls"
export PATH="$fixture/bin:$PATH"
cat > "$fixture/bin/docker" <<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$ARCHIVE_TEST_CALLS"
case "$*" in
    *mariadb-dump*) printf 'partial SQL'; exit 23 ;;
    *'exec -T database'*) cat >/dev/null; exit 24 ;;
esac
SH
chmod +x "$fixture/bin/docker"

if bash scripts/backup.sh "$fixture/backup" > "$fixture/backup.log" 2>&1; then
    printf '%s\n' 'A failed database dump made backup report success.' >&2
    exit 1
fi
test -z "$(ls -A "$fixture/backup")"
test "$(wc -l < "$ARCHIVE_TEST_CALLS")" -eq 1

for archive in database.sql.gz media.tar.gz private-media.tar.gz; do
    printf 'SELECT 1;\n' | gzip > "$fixture/restore/database.sql.gz"
    tar -C "$fixture/restore" -czf "$fixture/restore/media.tar.gz" media
    tar -C "$fixture/restore" -czf "$fixture/restore/private-media.tar.gz" private-media
    printf 'corrupt archive\n' > "$fixture/restore/$archive"
    : > "$ARCHIVE_TEST_CALLS"
    if bash scripts/restore.sh --confirm-destructive-restore "$fixture/restore" > "$fixture/restore.log" 2>&1; then
        printf 'Restore accepted corrupt archive %s.\n' "$archive" >&2
        exit 1
    fi
    test ! -s "$ARCHIVE_TEST_CALLS"
done

tar -C "$fixture/restore" -czf "$fixture/restore/private-media.tar.gz" private-media
gzip </dev/null > "$fixture/restore/database.sql.gz"
if bash scripts/restore.sh --confirm-destructive-restore "$fixture/restore" > "$fixture/restore.log" 2>&1; then
    printf '%s\n' 'Restore accepted an empty SQL archive.' >&2
    exit 1
fi
test ! -s "$ARCHIVE_TEST_CALLS"

printf 'SELECT 1;\n' | gzip > "$fixture/restore/database.sql.gz"
if bash scripts/restore.sh --confirm-destructive-restore "$fixture/restore" > "$fixture/restore.log" 2>&1; then
    printf '%s\n' 'Restore accepted a failed SQL import.' >&2
    exit 1
fi
test "$(wc -l < "$ARCHIVE_TEST_CALLS")" -eq 2
grep -q 'stop app' "$ARCHIVE_TEST_CALLS"
grep -q 'exec -T database' "$ARCHIVE_TEST_CALLS"

printf '%s\n' 'Backup and restore failure checks passed.'
