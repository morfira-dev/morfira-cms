#!/bin/sh
set -eu
umask 077

fixture_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
output_dir=${REHEARSAL_OUTPUT_DIR:-/tmp/morfira-wordpress-rehearsal}
mkdir -p "$output_dir"
env_file="$output_dir/compose.env"

if [ ! -s "$env_file" ]; then
    printf 'REHEARSAL_DB_PASSWORD=%s\nREHEARSAL_DB_ROOT_PASSWORD=%s\n' \
        "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" > "$env_file"
fi

compose() {
    docker compose --env-file "$env_file" -f "$fixture_dir/compose.yaml" "$@"
}

port=${REHEARSAL_WORDPRESS_PORT:-18120}
site="http://localhost:$port"
compose up -d --wait
compose exec -T -u www-data wordpress php /fixture/seed.php seed > "$output_dir/inventory.json"
wxr_temp=$(mktemp "$output_dir/.wxr.XXXXXX")
compose exec -T -u www-data wordpress php /fixture/seed.php export > "$wxr_temp"
mv "$wxr_temp" "$output_dir/wordpress-export.xml"
mkdir -p "$output_dir/uploads" "$output_dir/rest"
compose exec -T wordpress tar -C /var/www/html/wp-content/uploads -cf - . | tar -xf - -C "$output_dir/uploads"

for collection in pages posts media categories tags users; do
    curl --fail --silent --show-error --noproxy '*' \
        "$site/wp-json/wp/v2/$collection?per_page=100" > "$output_dir/rest/$collection.json"
done

printf 'WordPress REST: %s/wp-json/wp/v2\nWXR and uploads: %s\n' "$site" "$output_dir"
