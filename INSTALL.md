# Installation

> Stub — the full guide (local and cloud setup) is written in Slice 22.

## Prerequisites

- Docker Desktop (Compose v2) and GNU Make
- Optional, to run tools on the host: PHP 8.3+, Composer 2, Node 24

## Local quick start

```sh
make up          # mysql, backend (FrankenPHP :8080), frontend (Vite :5173), mailpit (:8025)
make test        # PHPUnit + Vitest
make lint        # PHP-CS-Fixer, PHPStan, ESLint, Prettier, tsc
```

`make` targets run inside the `backend` container when it is up, and on the host otherwise.

## Local secrets

Real keys never go into git. Create these git-ignored files:

`backend/.env.local`
```
CLERK_ISSUER=https://<your-instance>.clerk.accounts.dev
CLERK_JWKS_URL=https://<your-instance>.clerk.accounts.dev/.well-known/jwks.json
CLERK_SECRET_KEY=sk_test_...
```

`frontend/.env.local`
```
VITE_CLERK_PUBLISHABLE_KEY=pk_test_...
```

| Service | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8080/api |
| Mailpit | http://localhost:8025 |
| MySQL | localhost:3307 (argane / argane) |
