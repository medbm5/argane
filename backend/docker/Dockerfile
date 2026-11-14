# syntax=docker/dockerfile:1.7
FROM dunglas/frankenphp:1-php8.3 AS base

WORKDIR /app

RUN install-php-extensions \
        pdo_mysql \
        intl \
        zip \
        opcache \
        apcu \
        sodium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# ---------------------------------------------------------------------------
# Local development: code is bind-mounted, Caddy listens on :8080
# ---------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev \
    SERVER_NAME=:8080 \
    XDEBUG_MODE=off

RUN install-php-extensions xdebug \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

EXPOSE 8080

CMD ["sh", "-c", "composer install --no-interaction --prefer-dist && frankenphp run --config /etc/caddy/Caddyfile"]
