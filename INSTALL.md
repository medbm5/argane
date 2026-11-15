# Installation

Argane runs locally with Docker Compose. In the cloud it uses only free tiers:

| Piece | Local | Cloud |
|---|---|---|
| API (Symfony 7.4, FrankenPHP) + Messenger worker | `backend` + `worker` containers, :8080 | Render web service (Docker, free) |
| Database (MySQL 8) | `mysql` container, :3307 | Aiven for MySQL (free, TLS) |
| Frontend (React SPA) | `frontend` container (Vite), :5173 | Vercel (static) |
| Auth | Clerk development instance | same |
| Payments | Stripe test mode + Stripe CLI | Stripe test mode + webhook endpoint |
| Email | Mailpit, :8025 | any SMTP provider (optional) |

1. [Prerequisites](#prerequisites)
2. [Local setup](#local-setup)
3. [Accounts and keys](#accounts-and-keys)
4. [Demo data](#demo-data)
5. [Payments locally](#payments-locally)
6. [Google Sheets sync (optional)](#google-sheets-sync-optional)
7. [Tests and quality](#tests-and-quality)
8. [Cloud deployment](#cloud-deployment)
9. [Troubleshooting](#troubleshooting)

## Prerequisites

- Docker Desktop (Compose v2) and GNU Make
- A free [Clerk](https://clerk.com) account and a free [Stripe](https://stripe.com) account (test mode)
- Optional, to run tools on the host: PHP 8.3+ (`intl`, `pdo_mysql`, `sodium`), Composer 2, Node 24
- Optional: [Stripe CLI](https://docs.stripe.com/stripe-cli) (also available as a Compose service)

## Local setup

```sh
git clone <repo> argane && cd argane
# 1. create backend/.env.local and frontend/.env.local (next section)
make up          # mysql, backend (FrankenPHP :8080), worker, frontend (Vite :5173), mailpit (:8025)
make fixtures    # drop, migrate and seed the demo season (~30 s)
```

Open http://localhost:5173 and sign in with one of the [demo accounts](#demo-accounts).

| Service | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8080/api (health: `/api/health`) |
| Mailpit (emails) | http://localhost:8025 |
| MySQL | localhost:3307 (argane / argane) |

`make` targets run inside the `backend` container when it is up, and on the host
otherwise. `make help` lists them all.

### Without Docker

With PHP, Composer and Node on the host, and MySQL from Compose (`docker compose up -d mysql`):

```sh
cd backend && composer install
php bin/console doctrine:migrations:migrate -n
php -S 127.0.0.1:8099 -t public public/index.php          # API
php bin/console messenger:consume async scheduler_default  # worker (another terminal)

cd frontend && npm ci
VITE_API_URL=http://127.0.0.1:8099/api npm run dev         # SPA on :5173
```

## Accounts and keys

Real keys never go into git. `backend/.env` holds safe defaults. Put secrets in the
git-ignored `backend/.env.local` and `frontend/.env.local`.

### Clerk (authentication)

1. Create an application in the [Clerk dashboard](https://dashboard.clerk.com) with
   **Email** sign-in (verification code). Google is optional.
2. *Configure → API keys*: copy the publishable key (`pk_test_…`), the secret key
   (`sk_test_…`) and the Frontend API URL (`https://<instance>.clerk.accounts.dev`).

### Stripe (payments, test mode)

*Developers → API keys* in test mode: copy the publishable (`pk_test_…`) and secret
(`sk_test_…`) keys. The webhook secret comes from the Stripe CLI locally
([Payments locally](#payments-locally)) or from the webhook endpoint in the cloud.

### `backend/.env.local`

```
APP_SECRET=<any random 32+ chars>
CLERK_ISSUER=https://<instance>.clerk.accounts.dev
CLERK_JWKS_URL=https://<instance>.clerk.accounts.dev/.well-known/jwks.json
CLERK_SECRET_KEY=sk_test_...
CLERK_AUTHORIZED_PARTIES=http://localhost:5173
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...           # from `make stripe-secret`
# optional
ORGANIZER_EMAILS=you@example.com          # these emails get ROLE_ORGANIZER on first sign-in
BOX_OFFICE_EMAILS=desk@example.com
TICKET_QR_SECRET=<random>                 # signs ticket QR codes; falls back to APP_SECRET
```

### `frontend/.env.local`

```
VITE_CLERK_PUBLISHABLE_KEY=pk_test_...
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
# VITE_API_URL defaults to http://localhost:8080/api
```

### All backend variables

| Variable | Required | Purpose |
|---|---|---|
| `APP_SECRET` | yes | Symfony secret |
| `DATABASE_URL` | yes | `mysql://user:pass@host:port/db?serverVersion=8.0&charset=utf8mb4` |
| `DATABASE_SSL_CA_BASE64` | cloud | Aiven CA certificate, base64. The image decodes it at boot. |
| `DATABASE_SSL_CA`, `DATABASE_SSL_VERIFY` | no | CA file path and verification, set automatically in the image |
| `CORS_ALLOW_ORIGIN` | yes | Regex of the frontend origin |
| `CLERK_ISSUER`, `CLERK_JWKS_URL`, `CLERK_SECRET_KEY` | yes | Token verification and user lookup |
| `CLERK_AUTHORIZED_PARTIES` | yes | Comma-separated frontend origins allowed in the `azp` claim |
| `ORGANIZER_EMAILS`, `BOX_OFFICE_EMAILS` | no | Role bootstrap on first sign-in |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | yes | PaymentIntents and webhook signatures |
| `APP_CURRENCY` | no | `mad` (default) |
| `TICKET_QR_SECRET` | no | HMAC key for ticket QR codes (falls back to `APP_SECRET`) |
| `MAILER_DSN`, `MAILER_FROM` | no | `null://null` disables email |
| `APP_FRONTEND_URL` | no | Links in emails |
| `GOOGLE_SHEETS_SPREADSHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` | no | Sheets sync |
| `MESSENGER_TRANSPORT_DSN` | no | `doctrine://default?auto_setup=0` |
| `SYMFONY_TRUSTED_PROXIES` | cloud | `private_ranges,REMOTE_ADDR` behind Render's proxy |
| `PORT`, `RUN_MIGRATIONS` | cloud | Listening port (Render sets it). `RUN_MIGRATIONS=0` skips migrations at boot. |

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
[Clerk test emails](https://clerk.com/docs/testing/test-emails) (verification code **424242**,
development instances only). The first sign-in links the Clerk account to the seeded
user, its role and its orders.

| Role | Email | Has |
|---|---|---|
| Organizer (Studio) | `organisateur+clerk_test@example.com` | everything |
| Box office (Guichet, Contrôle) | `guichet+clerk_test@example.com` | counter sales, refunds, check-in |
| Customer | `client+clerk_test@example.com` | 3 orders, one past and checked in |

Any other account you create is a plain customer. Grant roles later with:

```sh
php bin/console app:user:grant someone@example.com ROLE_BOX_OFFICE
```

## Payments locally

Stripe confirms payments through a webhook (`POST /api/webhooks/stripe`). Locally the
Stripe CLI forwards it:

```sh
make payments        # starts the stripe-cli service (reads STRIPE_SECRET_KEY from backend/.env.local)
make stripe-secret   # prints whsec_…; put it in STRIPE_WEBHOOK_SECRET, then `make restart`
```

Pay with the test card `4242 4242 4242 4242`, any future date, any CVC. Use
`4000 0025 0000 3155` to see 3-D Secure. Without the CLI, payments still settle: the
order page reconciles with Stripe when it is read (see `docs/DECISIONS.md`).

Order confirmations and cancellations land in Mailpit (http://localhost:8025).

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

## Tests and quality

```sh
make test        # PHPUnit (unit, integration, functional, concurrency) + Vitest
make lint        # PHP-CS-Fixer, PHPStan level 8, ESLint, Prettier, tsc
```

Backend tests use the `argane_test` database and wrap each test in a transaction.
Create it once with
`APP_ENV=test php bin/console doctrine:database:create && APP_ENV=test php bin/console doctrine:migrations:migrate -n`. The concurrency test starts real
parallel PHP processes against MySQL.

### End-to-end smoke test (optional)

`frontend/e2e/purchase.spec.ts` buys a seat through the real stack: Clerk sign-in,
seat map, hold, tariffs, Stripe Payment Element, confirmation. It is not part of
`npm test` because it needs the servers and network access.

1. Start the stack (`make up`, or the API and Vite dev servers) with demo data seeded.
2. Keys: `VITE_CLERK_PUBLISHABLE_KEY` and `VITE_STRIPE_PUBLISHABLE_KEY` in
   `frontend/.env.local`, plus `CLERK_SECRET_KEY` and the Stripe test secret in
   `backend/.env.local`. Only `sk_test_` Clerk keys are accepted.
3. `cd frontend && npm run e2e`

The global setup creates the Clerk test user `client+clerk_test@example.com` if it is
missing. `+clerk_test` addresses sign in with the fixed code `424242` on development
instances, and the card is Stripe's `4242 4242 4242 4242`, so no inbox is needed and
nothing is charged. The test uses your installed Google Chrome. To use Playwright's
Chromium instead, run `npx playwright install chromium` and set
`E2E_BROWSER_CHANNEL=chromium`.
Overrides: `E2E_BASE_URL` (default `http://localhost:5173`), `E2E_API_URL` (default
`http://127.0.0.1:8099/api`), `E2E_CLERK_EMAIL`.

## Cloud deployment

Everything below runs on free plans. Order matters: the database first, then the API,
then the frontend, then wire their URLs into each other.

### 1. Database: Aiven for MySQL

1. [console.aiven.io](https://console.aiven.io) → *Create service* → **MySQL**, plan **Free**.
2. On the service page, note host, port, user (`avnadmin`), password and database
   (`defaultdb`), and **download the CA certificate** (`ca.pem`).
3. Build the URL:
   `mysql://avnadmin:<password>@<host>:<port>/defaultdb?serverVersion=8.0&charset=utf8mb4`
4. Base64 the CA on one line (as for the Google key above). This is `DATABASE_SSL_CA_BASE64`.

Aiven enforces `sql_require_primary_key`. Every table has a primary key, including
`messenger_messages`, and local MySQL runs with the same flag, so migrations behave
the same in both places.

### 2. Demo data in the cloud database (optional)

The fixtures are a dev tool, so load them from your machine straight into Aiven:

```sh
cd backend
export DATABASE_URL='mysql://avnadmin:…@<host>:<port>/defaultdb?serverVersion=8.0&charset=utf8mb4'
export DATABASE_SSL_CA=/path/to/ca.pem DATABASE_SSL_VERIFY=1
php bin/console doctrine:migrations:migrate -n
php -d memory_limit=1G bin/console doctrine:fixtures:load -n --no-debug
```

(PowerShell: `$env:DATABASE_URL='…'`, `$env:DATABASE_SSL_CA='C:\path\ca.pem'`, `$env:DATABASE_SSL_VERIFY='1'`.)

### 3. API: Render

1. Push the repository to GitHub.
2. [dashboard.render.com](https://dashboard.render.com) → *New → Blueprint* → pick the
   repository. Render reads [`render.yaml`](render.yaml): one Docker web service built
   from `backend/docker/Dockerfile` (the `prod` stage), health check `/api/health`.
3. Fill in the variables marked *sync: false* (see [All backend variables](#all-backend-variables)).
   Leave `CORS_ALLOW_ORIGIN`, `CLERK_AUTHORIZED_PARTIES` and `APP_FRONTEND_URL` as a
   placeholder such as `https://example.invalid` for now. You fill them in step 6.
   `APP_SECRET` and `TICKET_QR_SECRET` are generated by Render.
4. Deploy. At boot the container:
   - decodes the CA certificate,
   - runs pending migrations (retrying while Aiven wakes up),
   - starts FrankenPHP on `$PORT` and the Messenger worker under supervisord.

   `https://<service>.onrender.com/api/health` then answers
   `{"status":"ok","database":"ok"}`.

Render's free plan sleeps after 15 minutes idle; the first request takes about a
minute. The SPA shows an "Entracte prolongé" screen and retries in the meantime.

### 4. Stripe webhook

Stripe dashboard (test mode) → *Developers → Webhooks → Add endpoint*:

- URL: `https://<service>.onrender.com/api/webhooks/stripe`
- Events: `payment_intent.succeeded`, `payment_intent.payment_failed`

Copy its signing secret (`whsec_…`) into `STRIPE_WEBHOOK_SECRET` on Render.

### 5. Frontend: Vercel

1. [vercel.com/new](https://vercel.com/new) → import the repository, **Root Directory:
   `frontend`**. [`frontend/vercel.json`](frontend/vercel.json) sets the build, the SPA
   rewrite (every route → `index.html`), security headers and immutable caching of
   `/assets`.
2. Environment variables:
   ```
   VITE_API_URL=https://<service>.onrender.com/api
   VITE_CLERK_PUBLISHABLE_KEY=pk_test_...
   VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
   ```
3. Deploy, and note the URL (e.g. `https://argane.vercel.app`).

### 6. Wire the URLs together

On Render, set and redeploy:

```
CORS_ALLOW_ORIGIN=^https://argane\.vercel\.app$
CLERK_AUTHORIZED_PARTIES=https://argane.vercel.app,http://localhost:5173
APP_FRONTEND_URL=https://argane.vercel.app
DEFAULT_URI=https://<service>.onrender.com
```

Clerk development instances accept any origin, so the demo needs no change there. If
you move to a Clerk production instance, add the Vercel domain under *Configure → Domains*
and swap in the `pk_live`/`sk_live` keys.

### 7. Smoke check

1. Open the Vercel URL: the programme loads (after the cold start).
2. Sign in as `organisateur+clerk_test@example.com` (code 424242) and open **Studio**.
3. Buy a seat as `client+clerk_test@example.com` with card 4242. The order turns
   **Payée**, the ticket PDF downloads, and the seat shows as sold on the map.
4. Optional: point the e2e test at the cloud
   (`E2E_BASE_URL=https://argane.vercel.app E2E_API_URL=https://<service>.onrender.com/api npm run e2e`).

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Browser console: CORS error on `/api/*` | `CORS_ALLOW_ORIGIN` does not match the exact frontend origin (scheme, no trailing slash). It is a regex, so escape the dots. |
| Every API call returns 401 after sign-in | `CLERK_ISSUER`/`CLERK_JWKS_URL` point to another Clerk instance, or the frontend origin is missing from `CLERK_AUTHORIZED_PARTIES`. |
| `Cannot connect to MySQL using SSL` | Wrong or missing `DATABASE_SSL_CA_BASE64`, or the hostname in `DATABASE_URL` is not the one in the Aiven certificate. |
| `Unable to create or change a table without a primary key` | Only for tables created outside the migrations. Every Argane table has a primary key. |
| Orders stay *En attente de paiement* | Webhook not reaching the API (wrong URL or `STRIPE_WEBHOOK_SECRET`). Opening the order page still reconciles with Stripe. |
| First request takes ~1 minute | Render free cold start (and Aiven free power-off). Expected. |
| Holds never expire in the list | They do: expiry is lazy (`expires_at < now`). The scheduler only cleans up, and needs the worker. |
| PDF/email show a time one hour off the website | Known issue: PHP's time zone database and the browser disagree on Casablanca's UTC offset. See `docs/DECISIONS.md`. |
