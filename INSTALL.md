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

## Google Sheets sync (optional)

The box office can follow sales live in a Google Sheet: a `Ventes` tab (one row per
ticket) and a `Synthèse` tab (sold, capacity, fill, revenue and scans per date). The
sheet is fully rewritten after each sale, refund or cancellation (debounced 15 s) and
every hour as a safety net. Without configuration the feature stays off and the Studio
says so (Studio → Intégrations).

1. In [Google Cloud Console](https://console.cloud.google.com/), create a project (or
   reuse one) and enable the **Google Sheets API**.
2. *IAM & Admin → Service accounts → Create*. No role is needed. Open the account,
   *Keys → Add key → JSON*, and keep the downloaded file private.
3. Create a Google Sheet and **share it with the service account's email**
   (`…@….iam.gserviceaccount.com`) as **Editor**. The tabs are created on the first sync.
4. Put the configuration in `backend/.env.local` (never commit the key):

   ```
   GOOGLE_SHEETS_SPREADSHEET_ID=<the id in the sheet URL, between /d/ and /edit>
   GOOGLE_SERVICE_ACCOUNT_JSON_BASE64=<base64 of the JSON key file>
   ```

   Encode the key on one line: `base64 -w0 key.json` (Linux), `base64 -i key.json`
   (macOS), or in PowerShell
   `[Convert]::ToBase64String([IO.File]::ReadAllBytes("key.json"))`.
5. Check it: `php bin/console app:sheets:sync`, or *Synchroniser maintenant* in the Studio.
   Automatic syncs need the worker running (`docker compose up worker`).

## Services

| Service | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8080/api |
| Mailpit | http://localhost:8025 |
| MySQL | localhost:3307 (argane / argane) |
