#!/bin/sh
set -eu

project_name=morfira-e2e
export CMS_APP_SECRET=e2e-app-secret-that-is-not-used-outside-tests
export CMS_DATABASE_PASSWORD=e2e-database-password
export CMS_DATABASE_ROOT_PASSWORD=e2e-root-password
export CMS_PUBLIC_SITE_URL=http://localhost:18080
export CMS_PORT=18080

mkdir -p front/test-results front/playwright-report

cleanup() {
    docker compose -p "$project_name" -f compose.prod.yaml -f compose.e2e.yaml down --volumes --remove-orphans
}
trap cleanup EXIT INT TERM

cleanup
docker compose -p "$project_name" -f compose.prod.yaml -f compose.e2e.yaml build app e2e
docker compose -p "$project_name" -f compose.prod.yaml -f compose.e2e.yaml run --rm e2e
