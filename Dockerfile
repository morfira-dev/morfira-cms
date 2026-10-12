FROM node:22-bookworm-slim AS admin-builder

WORKDIR /build/front
COPY front/package.json front/package-lock.json ./
RUN npm ci
COPY front/ ./
COPY back/openapi/openapi.yaml /build/back/openapi/openapi.yaml
RUN npm run build

FROM composer:2 AS php-dependencies

WORKDIR /build/back
COPY back/composer.json back/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM dunglas/frankenphp:php8.4-bookworm AS runtime

RUN install-php-extensions pdo_mysql gd

WORKDIR /app/back
COPY back/ ./
COPY --from=php-dependencies /build/back/vendor ./vendor
COPY --from=admin-builder /build/front/dist ./public/admin
COPY docker/Caddyfile.production /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/morfira-entrypoint

RUN chmod 755 /usr/local/bin/morfira-entrypoint \
    && mkdir -p var public/media \
    && chown -R www-data:www-data var public/media

ENTRYPOINT ["morfira-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
