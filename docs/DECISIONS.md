# Decisions

Short records of the non-obvious choices. Each one: the decision, why, and what it costs.

| # | Decision |
|---|---|
| [1](#1-typescript-6-and-eslint-9) | TypeScript 6 and ESLint 9 |
| [2](#2-self-hosted-fonts) | Self-hosted fonts |
| [3](#3-clerk-for-sign-in-roles-in-our-database) | Clerk for sign-in, roles in our database |
| [4](#4-seat-map-geometry-and-availability-split-polled-with-etag) | Seat map: geometry and availability split, polled with ETag |
| [5](#5-two-reds-for-contrast) | Two reds for contrast |
| [6](#6-plain-controllers-not-api-platform) | Plain controllers, not API Platform |
| [7](#7-a-seat-is-sold-once-conditional-update) | A seat is sold once: conditional `UPDATE` |
| [8](#8-holds-expire-lazily) | Holds expire lazily |
| [9](#9-stripe-paymentintents-webhook-and-reconcile-on-read) | Stripe PaymentIntents, webhook and reconcile-on-read |
| [10](#10-google-sheets-full-rewrite-debounced) | Google Sheets: full rewrite, debounced |
| [11](#11-uuid-v7-and-integer-cents) | UUID v7 and integer cents |
| [12](#12-one-container-on-free-hosting) | One container on free hosting |
| [13](#13-current-time-zone-data-everywhere) | Current time-zone data everywhere |
| [14](#14-uploaded-posters-stored-in-mysql) | Uploaded posters stored in MySQL |
| [15](#15-one-hall-seats-switched-on-and-off) | One hall, seats switched on and off |

Answers to the challenge questions: [CHALLENGE.md](CHALLENGE.md).

---

### 1. TypeScript 6 and ESLint 9

- **Decision:** pin `typescript@~6.0` and `eslint@^9`.
- **Why:** typed linting (`typescript-eslint`) and `jsx-a11y` don't support TypeScript 7 / ESLint 10 yet.
- **Trade-off:** no native TS 7 compiler for now. Revisit when the plugins catch up.

### 2. Self-hosted fonts

- **Decision:** Anton, Space Grotesk and Permanent Marker ship in the bundle (`@fontsource`).
- **Why:** no third-party request (privacy, cold starts), cached with the versioned assets.
- **Trade-off:** slightly larger bundle.

### 3. Clerk for sign-in, roles in our database

- **Decision:** Clerk handles accounts and sessions. The API verifies the JWT locally (cached JWKS, issuer, `azp` allow-list). Roles live in `app_user`.
- **Why:** no passwords to store; no network call per request; authorization stays ours.
- **Trade-off:** a dependency on Clerk for sign-in.

### 4. Seat map: geometry and availability split, polled with ETag

- **Decision:** `/seatmap` (static, cached) and `/availability` (only taken seats, ETag), polled every 5 s.
- **Why:** most polls return `304` with no body.
- **Trade-off:** up to 5 s delay. Server-sent events would be the next step.

### 5. Two reds for contrast

- **Decision:** `--pop-red` for fills, `--red-text` (#c22f1c) for text.
- **Why:** the bright red is 3.6:1 on cream; the text red is 5.1:1 (WCAG AA).
- **Trade-off:** small red text is a little darker than the fills.

### 6. Plain controllers, not API Platform

- **Decision:** thin controllers calling application services; TypeScript types generated from OpenAPI.
- **Why:** almost every write is a business command (hold, pay, cancel, scan), not CRUD.
- **Trade-off:** Studio CRUD is written by hand.

### 7. A seat is sold once: conditional `UPDATE`

- **Decision:** a hold is one `UPDATE … WHERE status = 'AVAILABLE' OR hold expired`; if fewer rows change than asked, roll back and report the lost seats. A unique index on active tickets is the last guard.
- **Why:** atomic in MySQL, no app-level lock. `ParallelHoldTest` races real PHP processes.
- **Trade-off:** none worth noting at this scale.

### 8. Holds expire lazily

- **Decision:** every query treats `hold_expires_at < now` as free. The scheduler only cleans up.
- **Why:** the free container sleeps; correctness can't depend on a cron job.
- **Trade-off:** expired rows stay `HELD` until cleanup (invisible to users).

### 9. Stripe PaymentIntents, webhook and reconcile-on-read

- **Decision:** the API creates the PaymentIntent for the server-computed amount. The order is settled by the webhook **or** when the order page asks Stripe, under a row lock so only one settles it. A late payment whose seats are gone is refunded.
- **Why:** Stripe Checkout can't expire in under 30 min (our hold is 10); webhooks alone are slow on a sleeping host.
- **Trade-off:** two code paths to settle a payment (covered by a race test).

### 10. Google Sheets: full rewrite, debounced

- **Decision:** after a sale, refund or cancellation, rewrite both tabs 15 s later (deduplicated), plus hourly.
- **Why:** a few thousand rows; a full rewrite is idempotent and fixes any drift.
- **Trade-off:** more cells written than strictly needed.

### 11. UUID v7 and integer cents

- **Decision:** UUID v7 (`BINARY(16)`) ids; amounts in integer cents with the currency.
- **Why:** time-ordered compact indexes, no exposed sales volume; no rounding errors.
- **Trade-off:** ids are less readable, so orders also get `ARG-26-001268`.

### 12. One container on free hosting

- **Decision:** FrankenPHP and the Messenger worker run under supervisord in one Render container; migrations run at boot.
- **Why:** Render's free plan has no separate workers.
- **Trade-off:** web and worker scale together. No CSP on Vercel yet (Clerk and Stripe load from many domains); the API sends a strict one.

### 13. Current time-zone data everywhere

- **Decision:** the Docker image installs the `timezonedb` PHP extension.
- **Why:** Morocco moved to UTC+0 on 2026-09-20; PHP's bundled data didn't know, so PDFs showed an hour more than the site.
- **Trade-off:** a PHP outside Docker needs `pecl install timezonedb`.

### 14. Uploaded posters stored in MySQL

- **Decision:** `POST /api/studio/posters` stores JPEG/PNG/WebP (≤ 2 MB, type read from the bytes, SVG refused) in a `poster` table, served with a one-year immutable cache.
- **Why:** Render's free disk is wiped on every deploy and wake-up; a bucket is overkill for a demo.
- **Trade-off:** images in the database. Moving to object storage only touches `PosterLibrary`.

### 15. One hall, seats switched on and off

- **Decision:** the Studio shows the two fixed set-ups (Théâtre, Concert). The organizer deactivates or reactivates seats; upcoming dates follow where the seat is free. Sold or in-payment seats are never taken back.
- **Why:** one 400-seat venue doesn't need a layout generator, locks and clones.
- **Trade-off:** the generator API remains but is unused.
