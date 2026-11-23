#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 7 ]; then
    printf 'Usage: %s OUTPUT_DIR MORFIRA_ORIGIN WORDPRESS_ORIGIN MORFIRA_APP MORFIRA_DB WORDPRESS_APP WORDPRESS_DB\n' "$0" >&2
    exit 64
fi

runner="$(cd -- "$(dirname -- "$0")" && pwd)/public-html.py"
output=$1
mkdir -p "$output"
if [ -n "$(ls -A "$output")" ]; then
    printf '%s\n' 'Use a new or empty directory for the benchmark artifacts.' >&2
    exit 64
fi

sampler_pid=''
stop_sampler() {
    if [ -n "$sampler_pid" ]; then
        kill "$sampler_pid" 2>/dev/null || true
        wait "$sampler_pid" 2>/dev/null || true
        sampler_pid=''
    fi
}
trap stop_sampler EXIT

measure() {
    local platform=$1 url=$2 app=$3 database=$4 path=$5 concurrency=$6
    local artifact="$output/$platform-$path-c$concurrency"
    printf 'Measuring %s %s at concurrency %s\n' "$platform" "$path" "$concurrency"
    printf '%s\n%s\n' "$app" "$database" > "$artifact.memory.containers"
    docker stats --no-stream --format '{{json .}}' "$app" "$database" > "$artifact.memory.ndjson"
    docker stats --format '{{json .}}' "$app" "$database" >> "$artifact.memory.ndjson" &
    sampler_pid=$!
    python3 "$runner" "$url" --output "$artifact.json" --concurrency "$concurrency"
    stop_sampler
}

for path in rehearsal-about rehearsal-guide; do
    measure morfira "${2%/}/$path" "$4" "$5" "$path" 1
    measure wordpress "${3%/}/$path/" "$6" "$7" "$path" 1
    measure wordpress "${3%/}/$path/" "$6" "$7" "$path" 8
    measure morfira "${2%/}/$path" "$4" "$5" "$path" 8
done
