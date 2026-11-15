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

exec "$@"
