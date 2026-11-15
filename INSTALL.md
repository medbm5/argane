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

## Demo data

```sh
make fixtures    # drop, migrate and seed the database (deterministic, ~30 s)
```

The fixtures (`backend/src/DataFixtures/`) build a complete season for the fictional
**Théâtre Argane**, with dates relative to the day you load them:

| What | Details |
|---|---|
| Venue | Théâtre Argane, Agadir: *Configuration théâtre* (400 seats) and *Configuration concert* (fosse debout 300 + 160 seats). Two seats per layout are blocked for the technical desk. |
| Tariffs | Plein, Réduit (proof at entry), Groupe (10–30), Invitation (box office only, reason required) |
| Programme | 8 events, 18 performances: two in the past, one **cancelled** (*Rire à l'ouest*, first night, with refunds), one **nearly sold out** (*Tamra*, first night) |
| Sales | ≈ 3 000 tickets over 90 days, booking curve with an opening spike and a last-10-days rush, ≈ 65 % online / 35 % box office. Payments use the `FIXTURE` provider; nothing touches Stripe. |
| Check-in | ≈ 88 % of tickets of past performances are scanned |
| Customers | 80 fake customers (`*@example.com`, Faker seed 2026) |

Posters are typographic SVGs in `frontend/public/posters/`, regenerated with `npm run posters`.

### Demo accounts

Seeded users have no Clerk account yet. Sign up once in the app with one of these
[Clerk test emails](https://clerk.com/docs/testing/test-emails) (verification code **424242**):
the first sign-in links the Clerk account to the seeded user, its role and its orders.

| Role | Email | Has |
|---|---|---|
| Organizer (Studio) | `organisateur+clerk_test@example.com` | everything |
| Box office (Guichet, Contrôle) | `guichet+clerk_test@example.com` | counter sales, refunds, check-in |
| Customer | `client+clerk_test@example.com` | 3 orders, one past and checked in |

Any other account you create is a plain customer. Grant roles later with:

```sh
php bin/console app:user:grant someone@example.com ROLE_BOX_OFFICE
```

## Services

| Service | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8080/api |
| Mailpit | http://localhost:8025 |
| MySQL | localhost:3307 (argane / argane) |
