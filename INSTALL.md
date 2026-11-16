# Install

- [Run locally](#run-locally)
- [Keys](#keys)
- [Demo data and accounts](#demo-data-and-accounts)
- [Tests](#tests)
- [Optional: payments webhook, Google Sheets](#optional)
- [Deploy (free tiers)](#deploy-free-tiers)
- [Troubleshooting](#troubleshooting)

## Run locally

Needs Docker (Compose v2), GNU Make, and free [Clerk](https://clerk.com) and [Stripe](https://stripe.com) test accounts.

```sh
make up         # MySQL, API :8080, worker, frontend :5173, Mailpit :8025
make fixtures   # demo season (~30 s)
```

Open http://localhost:5173. `make help` lists every target.

| Service | URL |
|---|---|
| App | http://localhost:5173 |
| API | http://localhost:8080/api (health: `/api/health`, OpenAPI: `/api/doc.json`) |
| Emails | http://localhost:8025 |
| MySQL | localhost:3307 (argane / argane) |

## Keys

Secrets go only in the git-ignored `.env.local` files.

**`backend/.env.local`**

```ini
APP_SECRET=<random 32+ chars>
CLERK_ISSUER=https://<instance>.clerk.accounts.dev
CLERK_JWKS_URL=https://<instance>.clerk.accounts.dev/.well-known/jwks.json
CLERK_SECRET_KEY=sk_test_...
CLERK_AUTHORIZED_PARTIES=http://localhost:5173
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...   # from `make stripe-secret`
```

**`frontend/.env.local`**

```ini
VITE_CLERK_PUBLISHABLE_KEY=pk_test_...
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
# VITE_API_URL defaults to http://localhost:8080/api
```

<details>
<summary>All backend variables</summary>

| Variable | Needed | Purpose |
|---|---|---|
| `DATABASE_URL` | yes | `mysql://user:pass@host:port/db?serverVersion=8.0&charset=utf8mb4` |
| `DATABASE_SSL_CA_BASE64` | cloud | Aiven CA certificate (base64), decoded at boot |
| `CORS_ALLOW_ORIGIN` | yes | Regex of the frontend origin |
| `CLERK_*` | yes | JWT verification and user lookup |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | yes | Payments and webhook signatures |
| `TICKET_QR_SECRET` | no | HMAC key for QR codes (default: `APP_SECRET`) |
| `ORGANIZER_EMAILS`, `BOX_OFFICE_EMAILS` | no | Roles granted on first sign-in |
| `MAILER_DSN`, `MAILER_FROM`, `APP_FRONTEND_URL` | no | Emails (`null://null` disables) |
| `GOOGLE_SHEETS_SPREADSHEET_ID`, `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` | no | Sheets sync |
| `SYMFONY_TRUSTED_PROXIES`, `DEFAULT_URI`, `PORT`, `RUN_MIGRATIONS` | cloud | Proxy, absolute URLs, port, skip migrations with `0` |

</details>

## Demo data and accounts

`make fixtures` builds a season for the fictional Théâtre Argane: 8 shows, 18 dates (one cancelled, one nearly sold out), ~3,000 tickets over 90 days, 80 fake customers. Payments in the fixtures never touch Stripe.

Sign in with a [Clerk test email](https://clerk.com/docs/testing/test-emails), code **424242**:

| Role | Email |
|---|---|
| Organizer (everything) | `organisateur+clerk_test@example.com` |
| Box office (counter, check-in) | `guichet+clerk_test@example.com` |
| Customer | `client+clerk_test@example.com` |

Test card: `4242 4242 4242 4242` (3-D Secure: `4000 0025 0000 3155`). Grant a role: `php bin/console app:user:grant someone@example.com ROLE_BOX_OFFICE`.

## Tests

```sh
make test   # PHPUnit (unit, functional, concurrency) + Vitest
make lint   # PHP-CS-Fixer, PHPStan level 8, ESLint, Prettier, tsc
```

End-to-end purchase (real Clerk, Stripe and browser), against local or live:

```sh
cd frontend && npm run e2e
E2E_BASE_URL=https://argane-five.vercel.app E2E_API_URL=https://argane-api.onrender.com/api npm run e2e
```

## Optional

**Stripe webhook locally:** `make payments`, then `make stripe-secret` and put the `whsec_…` in `STRIPE_WEBHOOK_SECRET`. Without it, payments still settle when the order page is opened.

**Google Sheets:**

1. Google Cloud: enable the Sheets API, create a service account, add a JSON key.
2. Share a Google Sheet with the service account email as Editor.
3. Set `GOOGLE_SHEETS_SPREADSHEET_ID` (from the sheet URL) and `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` (`base64 -w0 key.json`).
4. Studio → Intégrations → *Synchroniser maintenant*.

## Deploy (free tiers)

```mermaid
flowchart LR
  A["1. Aiven MySQL"] --> B["2. Render API<br/>(render.yaml)"] --> C["3. Stripe webhook"] --> D["4. Vercel SPA<br/>(root: frontend)"] --> E["5. Wire URLs<br/>on Render"]
```

1. **Aiven:** create a free MySQL service; note the URL and download the CA (`base64 -w0 ca.pem`).
2. **Render:** *New → Blueprint* on the repo. Fill the `sync: false` variables (above). Boot runs migrations and starts web + worker.
3. **Stripe** (test mode): webhook to `https://<api>/api/webhooks/stripe` for `payment_intent.succeeded` and `payment_intent.payment_failed`; copy its secret to Render.
4. **Vercel:** import the repo with root directory `frontend`; set `VITE_API_URL`, `VITE_CLERK_PUBLISHABLE_KEY`, `VITE_STRIPE_PUBLISHABLE_KEY`.
5. **On Render:** set `CORS_ALLOW_ORIGIN`, `CLERK_AUTHORIZED_PARTIES`, `APP_FRONTEND_URL` to the Vercel URL, and `DEFAULT_URI` to the API URL.

Demo data in the cloud: run `doctrine:migrations:migrate` and `doctrine:fixtures:load` from the backend container with `DATABASE_URL`, `DATABASE_SSL_CA` and `DATABASE_SSL_VERIFY=1` pointing at Aiven.

## Troubleshooting

| Symptom | Fix |
|---|---|
| CORS error | `CORS_ALLOW_ORIGIN` must match the exact origin (regex: escape dots, no trailing slash). |
| 401 after sign-in | `CLERK_ISSUER`/`CLERK_JWKS_URL` from another instance, or origin missing from `CLERK_AUTHORIZED_PARTIES`. |
| MySQL SSL error | Wrong `DATABASE_SSL_CA_BASE64`, or host differs from the certificate. |
| Order stays pending | Webhook URL or secret wrong; opening the order page still settles it. |
| First request ~1 min | Render free cold start. Expected. |
| PDF time one hour off | Old PHP time-zone data; use Docker or `pecl install timezonedb`. |
