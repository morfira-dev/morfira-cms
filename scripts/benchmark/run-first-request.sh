#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 6 ]; then
    printf 'Usage: %s OUTPUT_DIR MORFIRA_ORIGIN WORDPRESS_ORIGIN MORFIRA_APP WORDPRESS_APP WORDPRESS_UPLOADS\n' "$0" >&2
    exit 64
fi

runner="$(cd -- "$(dirname -- "$0")" && pwd)/public-html.py"
output=$1
mkdir -p "$output"

wait_for_health() {
    local attempt
    for ((attempt=0; attempt<60; attempt++)); do
        if [ "$(docker inspect --format '{{.State.Health.Status}}' "$1")" = healthy ]; then
            return 0
        fi
        sleep 1
    done
    printf 'Container did not become healthy: %s\n' "$1" >&2
    return 1
}

first_request() {
    python3 "$runner" "$1" --output "$2" --requests 1 --warmup 0 --repeats 1
}

for iteration in 1 2 3; do
    docker restart -- "$4" >/dev/null
    wait_for_health "$4"
    first_request "${2%/}/rehearsal-about" "$output/morfira-first-$iteration.json"

    docker restart -- "$5" >/dev/null
    wait_for_health "$5"
    docker exec "$5" mkdir -p /var/www/html/wp-content/uploads
    tar -C "$6" -cf - . | docker exec -i "$5" tar -C /var/www/html/wp-content/uploads -xf -
    first_request "${3%/}/rehearsal-about/" "$output/wordpress-first-$iteration.json"
done
