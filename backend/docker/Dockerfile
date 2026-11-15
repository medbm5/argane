# syntax=docker/dockerfile:1.7
FROM dunglas/frankenphp:1-php8.3 AS base

WORKDIR /app

# timezonedb: the current IANA time zone database, newer than the copy bundled in PHP
# (Morocco left UTC+1 on 2026-09-20; see ADR-013).
RUN install-php-extensions \
        pdo_mysql \
        intl \
        zip \
        opcache \
        apcu \
        sodium \
        timezonedb

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

# ---------------------------------------------------------------------------
# Production (Render): code baked in, no dev dependencies, warmed cache.
# FrankenPHP and the Messenger worker run side by side under supervisord,
# because Render's free plan has no separate background workers (ADR in README).
# This is the last stage, so it is what `docker build` produces by default.
# ---------------------------------------------------------------------------
FROM base AS prod

# SERVER_NAME is derived from Render's $PORT by the entrypoint.
ENV APP_ENV=prod \
    APP_DEBUG=0

# The base image grants frankenphp cap_net_bind_service (to bind :80/:443 without root).
# Platforms that drop capabilities (Render) then refuse to exec it with EPERM. We listen
# on a high $PORT, so remove the file capability.
RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/prod.ini "$PHP_INI_DIR/conf.d/zz-argane.ini"
COPY docker/supervisor/argane.conf /etc/supervisor/conf.d/argane.conf
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/argane-entrypoint

# Dependencies first, so code changes reuse this layer.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console cache:warmup \
    && chmod -R a+rwX var

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/argane-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/argane.conf"]
