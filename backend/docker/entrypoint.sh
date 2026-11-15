#!/bin/sh
# Production entrypoint (Render). Prepares the environment, then hands over to CMD.
set -eu

# Aiven MySQL only accepts TLS. The CA certificate arrives base64-encoded in an env var
# (Render has no secret files on the free plan); write it out and point Doctrine at it.
if [ -n "${DATABASE_SSL_CA_BASE64:-}" ]; then
    printf '%s' "$DATABASE_SSL_CA_BASE64" | base64 -d > /tmp/database-ca.pem
    chmod 0644 /tmp/database-ca.pem
    export DATABASE_SSL_CA=/tmp/database-ca.pem
    export DATABASE_SSL_VERIFY="${DATABASE_SSL_VERIFY:-true}"
fi

# Render injects the port to listen on; FrankenPHP reads SERVER_NAME (":<port>" = plain
# HTTP on all interfaces, TLS is terminated by Render).
export SERVER_NAME=":${PORT:-8080}"

# Apply pending migrations before serving. Retries cover a database that is still
# waking up (Aiven free powers off when idle). Opt out with RUN_MIGRATIONS=0.
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    attempt=1
    until php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing; do
        if [ "$attempt" -ge 5 ]; then
            echo "argane: migrations failed after $attempt attempts" >&2
            exit 1
        fi
        echo "argane: migrations failed (attempt $attempt), retrying in $((attempt * 5))s" >&2
        sleep $((attempt * 5))
        attempt=$((attempt + 1))
    done
fi

exec "$@"
