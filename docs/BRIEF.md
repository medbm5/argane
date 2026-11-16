# Théâtre Argane — Build brief (archive)

> **Archive.** This is the original build brief that drove the work slice by slice. It is kept as written; the current state is in the [README](../README.md), [Architecture](ARCHITECTURE.md) and [Decisions](DECISIONS.md). Some details changed since (for example the hall configurations, see decision 15).

> Online and box-office ticketing for a 400-seat performance hall in Agadir.
> Symfony 7.4 API + MySQL · React (Vite, TypeScript) · Stripe (test mode) · Clerk auth · Google Sheets sync.
> Backend on Render (Docker, free), database on Aiven (free MySQL), frontend on Vercel.

This README is the build brief for Claude Code. It describes the product, the architecture, the design system and an ordered list of **slices**. Each slice is a vertical piece of work made of **small Conventional Commits**. Work through the slices in order, one commit per bullet.

The project is based on the EMC PFE Challenge, *Sujet 1 — Billetterie d'une salle de spectacle*. Everything the brief requires is covered (see [Challenge coverage](#challenge-coverage)); everything it does not specify is decided here using current best practice.

---

## Demo walkthrough

**Live demo:** https://argane-five.vercel.app (API health: https://argane-api.onrender.com/api/health).
The API runs on Render's free plan and sleeps when idle, so the first visit can take
up to a minute while "Le rideau se lève…" is shown; after that it is fast.

| Role | Sign in with | Code |
|---|---|---|
| Spectator | `client+clerk_test@example.com` | 424242 |
| Organizer (Studio, box office, check-in) | `organisateur+clerk_test@example.com` | 424242 |

Or run it locally with [INSTALL.md](../INSTALL.md) (`make up && make fixtures`, about two minutes),
then follow the season from three seats: the spectator's, the box office's and the
organizer's. The demo accounts use Clerk test emails with the fixed code **424242**.
Payments use Stripe's test card **4242 4242 4242 4242**.

### 1. A spectator books a seat

| | |
|---|---|
| ![Home page](screenshots/01-home.jpg) | **Home.** The season at a glance, with a quick search by show, month and category. |
| ![Programme](screenshots/02-programme.jpg) | **Programmation.** Filter by category and month; each show lists its next date, its price range and whether it is on sale. |
| ![Show page](screenshots/03-event.jpg) | **A show.** Synopsis, duration, age rating and every date with its hall configuration. |
| ![Seat map](screenshots/04-seat-map.jpg) | **Seat map.** Live availability (polled with ETags), zones coloured by price, keyboard and screen-reader friendly. Picking seats holds them for 10 minutes. |

Sign in as `client+clerk_test@example.com`, pick two seats, choose *Plein* and *Réduit*,
and pay with the test card. The order is confirmed within seconds, from the Stripe
webhook or, if it is late, from reconciliation when the order is read.

| | |
|---|---|
| ![Wallet](screenshots/05-wallet.jpg) | **Mes billets.** Upcoming and past orders, PDF download. Online orders can be cancelled up to 48 h before the show, with an automatic refund. |
| ![Ticket with QR code](screenshots/06-ticket-qr.jpg) | **Tickets.** One signed QR code per seat (`ARG1.<id>.<HMAC>`), no personal data. The same layout is used in the PDF and the confirmation email. |

On a phone, the seat map opens zoomed in and pans with one finger:

<img src="screenshots/11-mobile-seat-map.jpg" alt="Seat map on a phone" width="260">

### 2. The box office sells and checks tickets

Sign in as `guichet+clerk_test@example.com`.

| | |
|---|---|
| ![Box office](screenshots/09-box-office.jpg) | **Guichet.** The next 14 days, the same seat map, every tariff the box office may sell (including *Invitation*, with a reason), cash or card. Counter refunds are settled here too. |
| ![Check-in](screenshots/10-check-in.jpg) | **Contrôle.** The phone camera scans QR codes at the door. A green or red verdict comes back in under a second, with a live count of admissions. A ticket only gets in once, even with two doors scanning at the same moment. |

### 3. The organizer runs the season

Sign in as `organisateur+clerk_test@example.com` and open **Studio**.

| | |
|---|---|
| ![Dashboard](screenshots/07-studio-dashboard.jpg) | **Tableau de bord.** Revenue, tickets, average basket and refunds against the previous period; upcoming fill rate by zone; dates selling slower than usual; sales per day and revenue per tariff and channel. |
| ![Sales](screenshots/08-studio-sales.jpg) | **Ventes.** Every order, filterable and exportable to CSV. The same data is mirrored to Google Sheets within 15 seconds of each sale. |

Also in the Studio: shows and dates (publish, put on sale, **cancel a date**: every
ticket is voided, online payments are refunded and customers are emailed), hall
configurations (locked once used, clone to edit), price grids per zone and tariff
rules.

### Things to try

- **Two buyers, one seat.** Open the same date in two browsers and pick the same seat.
  The second hold is refused, and the seat turns red in the first browser within 5 seconds.
- **A payment that never comes.** Hold seats and close the tab. Ten minutes later the
  seats are back on sale, even if the worker is asleep.
- **Cancel a performance** in the Studio, then look at *Mes billets*, the refunds in
  Stripe's test dashboard and the emails in Mailpit (http://localhost:8025).
- **A reused ticket.** Scan the same QR code twice at *Contrôle*.

Screenshots are regenerated from the demo data with `cd frontend && npm run screenshots`.

---

## Table of contents

0. [Demo walkthrough](#demo-walkthrough)
1. [Working agreement for Claude Code](#working-agreement-for-claude-code)
2. [The brand: Théâtre Argane](#the-brand-théâtre-argane)
3. [Product scope](#product-scope)
4. [Architecture](#architecture)
5. [Domain model](#domain-model)
6. [Business rules](#business-rules)
7. [API surface](#api-surface)
8. [Frontend](#frontend)
9. [Design system](#design-system)
10. [Environment variables](#environment-variables)
11. [Slices and commits](#slices-and-commits)
12. [Challenge coverage](#challenge-coverage)
13. [Decisions to record](#decisions-to-record)

---

## Working agreement for Claude Code

- Work **one slice at a time**, in order. At the end of each slice, stop and print a short summary: what was built, how to try it, what is left.
- **One commit per bullet** in the slice. Commits are small and focused. Never squash, never rewrite history: the git log must show steady progress from day one to delivery.
- Commit format: [Conventional Commits](https://www.conventionalcommits.org/) — `feat`, `fix`, `chore`, `docs`, `test`, `refactor`, `build`, `ci`, `style`, `perf`, with a scope: `feat(booking): ...`.
- Before every commit touching code: run the relevant tests and linters (`make test`, `make lint`). Do not commit a red build.
- Never commit secrets. All config comes from env vars; `.env` holds safe defaults only, `.env.local` is git-ignored.
- When you make a non-obvious choice, add an entry to `docs/DECISIONS.md` in the same commit.
- Keep AI traces: save session notes/exports in `docs/ai-traces/` (zipped at delivery).
- When something in this README is ambiguous, prefer the simplest option that keeps the business rules safe, and record it in `DECISIONS.md`.

---

## The brand: Théâtre Argane

A fictional demo venue. All names, artists and sponsors below are invented.

| | |
|---|---|
| Name | **Théâtre Argane** |
| City | Agadir, Morocco — Boulevard du 20 Août (fictional address) |
| Since | 1974 |
| Capacity | 400 seats (théâtre configuration) |
| Tagline | *Chaque place raconte une histoire.* |
| Voice | Warm, precise, a little ceremonial. Plain verbs, sentence case. Never salesy. |
| Currency | MAD (configurable via `APP_CURRENCY`) |
| UI language | French, strings centralized for later i18n |

**Hall configurations (layouts)**

| Layout | Zones | Capacity |
|---|---|---|
| *Configuration théâtre* (default) | Orchestre (rows A–L × 20 = 240), Corbeille (rows M–R × 20 = 120), Loges (8 boxes × 5 = 40) | 400 seated |
| *Configuration concert* | Fosse debout (standing, 300), Corbeille (120 seated), Loges (40 seated) | 460 |

Some Orchestre seats are flagged accessible (row A, seats 1–2 and 19–20) and two seats per layout are blocked for the technical desk.

**Demo programme (season 26/27)**

| Event | Category | Notes |
|---|---|---|
| *Tamra — nuits gnaoua* | Concert | Concert layout, 3 performances, one nearly sold out |
| *Le Misanthrope* (Molière) | Théâtre | 4 performances |
| *Cyrano de Bergerac* (Rostand) | Théâtre | 3 performances, one in the past |
| *Quatuor Ambre — Les Saisons* | Concert | Théâtre layout, 2 performances |
| *L'Atlantique, mémoire des marins* | Conférence | 1 performance, free invitation-heavy |
| *Ahwach, corps et cercle* | Danse | 2 performances |
| *Rire à l'ouest* | Humour | 2 performances, one **cancelled** (to demo mass refunds) |
| *Jazz sur la baie* | Concert | Concert layout, 1 past performance |

Posters are generated as SVG (typographic, duotone, no stock photos) and stored in `frontend/public/posters/`. No real brand logos anywhere; the partners strip on the landing page uses invented sponsors (*Atlas Imprimerie*, *Souss Radio*, *Maison Tifaout*, *Café Bab Al Bahr*).

---

## Product scope

### Roles

| Role | Who | Can |
|---|---|---|
| `ROLE_CUSTOMER` | Anyone who signs up | Browse, pick seats, pay online with Stripe, download tickets, cancel eligible orders |
| `ROLE_BOX_OFFICE` | Guichetier | Everything above + sell at the counter (cash / card terminal / invitation), refund counter orders, scan tickets at the door |
| `ROLE_ORGANIZER` | Venue owner / admin | Everything above + the **Studio**: layouts, events, performances, tariffs, price grids, cancellations, analytics, exports, integrations |

Role hierarchy: `ORGANIZER > BOX_OFFICE > CUSTOMER`. Roles live in our database, not in Clerk.

### Features

**Public**
- Landing page with the season, upcoming performances, categories and venue story.
- Programme with filters (category, month).
- Event page with performances, prices from, duration, age rating.
- Immersive seat selection on a live hall plan (dark "house lights down" mode), standing zones by quantity.

**Customer**
- Temporary seat hold with visible countdown.
- Checkout with Stripe Payment Element (test mode), tariff per seat.
- Order confirmation, ticket PDFs with QR codes, "Mes billets" wallet.
- Order cancellation with refund, within the policy.
- Confirmation and cancellation emails.

**Box office**
- Point-of-sale screen: pick performance, pick seats on the same plan, choose tariffs (including restricted ones), take cash / card terminal / invitation, print tickets.
- Counter refunds.
- Door check-in scanner (camera QR scanning, works on a phone).

**Organizer Studio**
- Hall layouts: generate zones and seats from a form (rows × seats, labels, accessible / blocked seats), preview the plan, clone a layout to edit it.
- Events and performances: create, schedule, choose layout per performance, open/close sales, publish, cancel (with automatic refunds).
- Tariffs and price grids: zone × tariff per performance, "apply to all performances" action.
- BI dashboard (see below), sales table with filters, CSV export.
- Google Sheets sync status and manual "Sync now".

**BI dashboard**
- KPIs with period comparison: net revenue, tickets sold, average fill rate, average order value, refund rate, check-in rate.
- Fill rate per performance and per zone (gauge + stacked bars).
- Revenue by tariff and by channel (online vs box office).
- Sales over time (daily) and booking curve (tickets sold vs days before show).
- Seat heatmap: which seats sell first for a performance.
- Top events by revenue and by fill rate.
- Upcoming performances at risk (low fill X days before).

### Out of scope for v1 (stretch)
Live seat updates via Mercure (polling is used in v1, see Slice 20), promo codes, waitlists, poster uploads to object storage, multi-venue tenancy UI (the schema is ready for it), an AI assistant in the Studio.

---

## Architecture

### Repository layout (monorepo)

```
argane-billetterie/
├── backend/                 # Symfony 7.4 API (PHP 8.3, FrankenPHP)
├── frontend/                # React + Vite + TypeScript
├── docs/
│   ├── DECISIONS.md         # Answers to the challenge questions + ADR-style notes
│   ├── ARCHITECTURE.md
│   ├── note-conceptuelle.pdf
│   └── ai-traces/
├── docker-compose.yml       # Local dev: mysql, backend, worker, frontend, mailpit, stripe-cli
├── Makefile                 # make up, make test, make lint, make fixtures, ...
├── INSTALL.md
└── README.md
```

### Backend: modular monolith

One deployable, split into modules by business capability. Each module has the same internal layering. Modules talk through application services or domain events, never by reaching into each other's infrastructure.

```
backend/src/
├── Shared/          # Money, Clock, Uuid, problem+json errors, pagination, base exceptions
├── Identity/        # User, Clerk JWT authenticator, roles, JIT provisioning
├── Venue/           # Venue, HallLayout, Zone, Seat, layout generator
├── Catalog/         # Event, Performance, publication, inventory generation
├── Pricing/         # Tariff, PerformancePrice, tariff eligibility policy
├── Booking/         # Holds, inventory locking, Order, Ticket
├── Payment/         # Stripe gateway, webhooks, Payment, Refund
├── Ticketing/       # QR tokens, PDF rendering, check-in
├── Reporting/       # Read-only analytics queries (DBAL), CSV exports
├── Integration/     # Google Sheets sync
└── Notification/    # Emails
```

Inside each module:

```
<Module>/
├── Domain/          # Entities, value objects, enums, domain events, repository interfaces
├── Application/     # Use cases (Command + Handler), queries, DTOs, policies
├── Infrastructure/  # Doctrine repositories, external clients (Stripe, Google, Clerk)
└── Api/             # Controllers, request/response DTOs, voters
```

Key technical choices:

| Concern | Choice |
|---|---|
| Framework | Symfony 7.4, PHP 8.3 |
| HTTP | Plain controllers with `#[MapRequestPayload]` DTOs + Validator. No API Platform: the checkout/hold flows are command-shaped, not CRUD |
| API docs | `nelmio/api-doc-bundle` → OpenAPI at `/api/doc.json`; frontend types generated with `openapi-typescript` |
| Errors | RFC 7807 `application/problem+json` everywhere, with a stable `code` field (`SEAT_UNAVAILABLE`, `HOLD_EXPIRED`, …) |
| IDs | UUID v7 (`symfony/uid`), public order references like `ARG-26-000123` |
| Money | Integer minor units (`amountCents`) + currency, `Money` value object |
| Time | Stored in UTC, displayed in `Africa/Casablanca`; `ClockInterface` injected for tests |
| Async | Symfony Messenger, Doctrine transport (MySQL) — no Redis needed |
| Scheduling | Symfony Scheduler for hold cleanup and periodic Sheets sync |
| Concurrency | Conditional atomic `UPDATE` on the inventory row + row locks for standing stock + unique indexes as a final guard |
| PDF / QR | `dompdf/dompdf` + `endroid/qr-code` |
| Payments | `stripe/stripe-php`, PaymentIntents + Payment Element, webhook as source of truth |
| Auth | Clerk (free tier). Backend verifies RS256 JWTs against Clerk JWKS |
| CORS | `nelmio/cors-bundle` |
| Rate limiting | `symfony/rate-limiter` on hold and checkout endpoints |
| Quality | PHPUnit, PHPStan (level 8), PHP-CS-Fixer, Rector (optional) |
| Runtime | FrankenPHP (single container), supervisord runs the Messenger worker alongside |

### Frontend

```
frontend/src/
├── app/                # Router, providers (Clerk, QueryClient, Stripe), layouts, guards
├── design-system/      # tokens.css, fonts, primitives, motion presets
├── features/
│   ├── catalog/        # Landing, programme, event page
│   ├── seatmap/        # SVG hall plan, selection store, legend, zoom
│   ├── booking/        # Hold timer, cart summary
│   ├── checkout/       # Stripe Payment Element, confirmation
│   ├── tickets/        # Wallet, ticket stub, PDF download
│   ├── box-office/     # POS screen
│   ├── check-in/       # QR scanner
│   ├── studio/         # Back-office CRUD
│   └── analytics/      # BI dashboard
├── lib/                # api client (generated types), money, dates, errors
└── pages/              # Thin route components composing features
```

| Concern | Choice |
|---|---|
| Build | Vite, React 19, TypeScript strict |
| Routing | React Router (data routers) |
| Server state | TanStack Query |
| Client state | Zustand (seat selection, POS cart) |
| Styling | Tailwind CSS v4 driven by CSS variables from `tokens.css` |
| Primitives | Radix UI (dialog, popover, tabs, select, toast) styled with our tokens |
| Forms | react-hook-form + zod |
| Motion | `motion` (Framer Motion), respects `prefers-reduced-motion` |
| Charts | Recharts for standard charts; custom SVG for gauge, dot-matrix sparklines, seat heatmap |
| Seat map | Custom SVG + `react-zoom-pan-pinch` (400–500 nodes is comfortable for SVG) |
| Payments | `@stripe/react-stripe-js` with an `appearance` theme matching our tokens |
| Auth | `@clerk/clerk-react` with themed `appearance` |
| QR scanning | `@zxing/browser` |
| Tests | Vitest + Testing Library; Playwright for a smoke purchase flow |

### Deployment

```
Vercel (frontend SPA) ──HTTPS──▶ Render web service (Docker: FrankenPHP + Messenger worker)
                                          │
                                          ├──▶ Aiven MySQL (TLS required)
                                          ├──▶ Stripe API (test mode) ◀── webhooks
                                          ├──▶ Clerk JWKS / Backend API
                                          └──▶ Google Sheets API (service account)
```

Free-tier notes to handle in code:
- **Render free** sleeps after inactivity, cold start ≈ 30–60 s. The frontend shows a themed "Le rideau se lève…" loader and retries `/api/health` with backoff. Stripe retries webhooks, so a sleeping backend does not lose payments.
- Render free has no separate background workers: the image runs FrankenPHP and `messenger:consume` under supervisord in the same container. Holds expire **lazily** (every query treats `holdExpiresAt < now` as available), so correctness never depends on the worker being awake.
- **Aiven MySQL** requires TLS: download the CA cert, pass it via `DATABASE_SSL_CA` (base64 env decoded at boot to a file) and set `PDO::MYSQL_ATTR_SSL_CA` in Doctrine options. Aiven enforces `sql_require_primary_key`: every table, including `messenger_messages` and `doctrine_migration_versions`, must have a primary key.
- The container listens on `$PORT` (Render injects it).
- Vercel needs an SPA rewrite (`vercel.json`: all routes → `/index.html`).

---

## Domain model

All IDs are UUID v7 unless stated. All money is in minor units.

### Venue module

**Venue** — `id, name, slug, city, address, timezone`
**HallLayout** — `id, venue, name, isDefault, archivedAt, createdAt`
A layout becomes **locked** as soon as one performance with sales uses it. Editing a locked layout means cloning it (`Clone & edit`), which protects sold tickets from geometry changes.
**Zone** — `id, layout, code, name, kind (SEATED|STANDING), capacity (STANDING only), colorToken, sortOrder, shape (SVG path or rect, in layout coordinates)`
**Seat** — `id, zone, rowLabel, number, x, y, isAccessible, isBlocked` — unique `(zone_id, row_label, number)`.
Layout coordinates use a fixed 1000 × 700 viewBox; the stage sits at the top.

### Catalog module

**Event** — `id, venue, slug, title, subtitle, category (CONCERT|THEATRE|CONFERENCE|DANCE|COMEDY), description, posterPath, durationMinutes, ageRating, status (DRAFT|PUBLISHED|ARCHIVED), createdBy`
**Performance** — `id, event, layout, startsAt, doorsAt, salesOpenAt, salesCloseAt, status (SCHEDULED|ON_SALE|SALES_CLOSED|CANCELLED), cancelledAt, cancelReason`
Status `PAST` is derived from `startsAt`, not stored.

**PerformanceSeat** (inventory, one row per seat per performance, created when the performance is published)
`id, performance, seat, zone, status (AVAILABLE|HELD|SOLD|BLOCKED), holdId, holdExpiresAt, ticketId, updatedAt`
Unique `(performance_id, seat_id)`, index `(performance_id, status)`.

**PerformanceZoneStock** (standing zones) — `id, performance, zone, capacity, soldCount`
Active holds on standing zones are rows in `StandingHoldLine`.

### Pricing module

**Tariff** — `id, venue, code (FULL|REDUCED|GROUP|INVITATION|custom), label, channels (json: ONLINE, BOX_OFFICE), allowedRoles (json), minQuantity, maxQuantity, requiresProofAtEntry, isFree, active`
**PerformancePrice** — `id, performance, zone, tariff, amountCents` — unique `(performance_id, zone_id, tariff_id)`. A missing row means the tariff is not offered in that zone.

### Booking module

**Hold** — `id, performance, user, channel (ONLINE|BOX_OFFICE), status (ACTIVE|CONVERTED|RELEASED|EXPIRED), expiresAt, createdAt`
**StandingHoldLine** — `id, hold, zone, quantity`
**Order** — `id, reference, customer (nullable for walk-ins), walkInName, walkInEmail, seller (staff user, nullable), channel, status (PENDING_PAYMENT|PAID|CANCELLED|REFUNDED|PARTIALLY_REFUNDED|EXPIRED|FAILED), totalCents, currency, hold, createdAt, paidAt, cancelledAt`
**Ticket** — `id, order, performance, performanceSeat (nullable for standing), zone, tariff, unitPriceCents, tariffLabel, seatLabel, zoneLabel, status (VALID|CHECKED_IN|CANCELLED|REFUNDED), qrNonce, checkedInAt, checkedInBy`
Price, tariff label and seat label are **snapshots** taken at sale time.
Final safety net: a generated column `active_seat_key = IF(status IN ('VALID','CHECKED_IN'), performance_seat_id, NULL)` with a **unique index**. MySQL allows many NULLs, so a seat can only have one live ticket per performance even if application code is wrong.

### Payment module

**Payment** — `id, order, provider (STRIPE|CASH|CARD_TERMINAL|NONE|FIXTURE), providerRef, amountCents, status (PENDING|SUCCEEDED|FAILED|CANCELLED), createdAt`
**Refund** — `id, order, payment, amountCents, reason, providerRef, status, requestedBy, createdAt`
**StripeWebhookEvent** — `id (Stripe event id, PK), type, processedAt` — idempotency.

### Identity module

**User** — `id, clerkUserId (unique), email, displayName, roles (json), createdAt`
On the first authenticated request with an unknown `sub`, the backend fetches the user from Clerk's Backend API and creates it (JIT provisioning). Emails listed in `ORGANIZER_EMAILS` / `BOX_OFFICE_EMAILS` get those roles at creation. `bin/console app:user:grant <email> ROLE_BOX_OFFICE` changes roles later.

### Integration module

**SheetSyncState** — `id, spreadsheetId, lastSyncedAt, lastStatus, lastError, rowCount`

---

## Business rules

### Selling a seat once per performance, even under concurrency

1. **Hold** (`POST /api/holds`), in one transaction:
   ```sql
   UPDATE performance_seat
      SET status = 'HELD', hold_id = :hold, hold_expires_at = :exp
    WHERE performance_id = :perf
      AND id IN (:ids)
      AND (status = 'AVAILABLE' OR (status = 'HELD' AND hold_expires_at < :now));
   ```
   If affected rows ≠ requested count → rollback, return `409 SEAT_UNAVAILABLE` with the list of seats no longer available. InnoDB row locks serialize two concurrent buyers: the second one re-evaluates the `WHERE` after the first commits and matches nothing.
2. **Standing zones**: `SELECT … FOR UPDATE` on `performance_zone_stock`, compute `capacity − soldCount − activeHeldQuantity`, insert the `StandingHoldLine` or reject.
3. **Sale** (payment confirmed): `UPDATE … SET status='SOLD', ticket_id=… WHERE hold_id = :hold AND status='HELD'`. Tickets are inserted in the same transaction; the unique `active_seat_key` index is the last guard.

### Temporary hold
- Default TTL 10 minutes (`HOLD_TTL_SECONDS`). The countdown is shown in the UI from the server's `expiresAt`.
- Max 10 seats per online hold (30 at the box office).
- A user has at most one active hold per performance; creating a new one releases the old one.
- Expiry is lazy (queries treat expired holds as free) plus a scheduled cleanup that marks holds `EXPIRED` and cancels their pending PaymentIntents.

### Payment that does not succeed
- Failure or abandonment: the order stays `PENDING_PAYMENT` until the hold expires, then becomes `EXPIRED`; seats return to `AVAILABLE`; the PaymentIntent is cancelled.
- Late success (webhook `payment_intent.succeeded` after expiry): try to re-acquire the same seats atomically. If they are still free, complete the sale. If not, **refund automatically** and email the customer. Never sell a seat twice to honor a late payment.

### Customer cancellation and refunds
- Online orders can be cancelled by the customer up to **48 h before** the performance. Full refund via Stripe Refund API. Tickets → `REFUNDED`, seats → `AVAILABLE`.
- Invitations (free) can be cancelled but there is nothing to refund.
- Box-office orders are refunded at the counter by `ROLE_BOX_OFFICE` (refund recorded with provider `CASH` / `CARD_TERMINAL`).
- Refunds are idempotent (Stripe idempotency key = refund id).

### Performance cancellation by the organizer
- Status → `CANCELLED`, sales stop immediately, active holds released.
- A Messenger job refunds every paid order of that performance: Stripe orders automatically, box-office orders flagged `REFUND_AT_COUNTER` and listed in the box-office screen.
- All tickets → `CANCELLED` (QR codes stop validating). Customers receive an email.

### Who can sell which tariff

| Tariff | Online (customer) | Box office | Organizer | Notes |
|---|---|---|---|---|
| Plein | ✓ | ✓ | ✓ | |
| Réduit | ✓ | ✓ | ✓ | `requiresProofAtEntry`: shown on ticket and at check-in |
| Groupe | ✓ (min 10 seats) | ✓ | ✓ | Min 10, max 30 |
| Invitation | ✗ | ✓ with a reason | ✓ | Free, counted separately in revenue reports |

Enforced server-side by `TariffEligibilityPolicy` (channel + role + quantity); the UI only displays what the API says is allowed.

### Price changes
Changing a `PerformancePrice` never affects sold tickets: `Ticket.unitPriceCents` is a snapshot. Active holds are re-priced at checkout time; the UI shows the new price before payment.

### Tickets and QR codes
- QR payload: `ARG1.<ticketId base58>.<signature>` where the signature is a truncated `HMAC-SHA256(TICKET_QR_SECRET, "ARG1|ticketId|qrNonce")`; the nonce stays server-side, so a reissued ticket invalidates the old QR. No personal data in the QR.
- Check-in validates signature, status `VALID`, performance date (door opens − 2 h to end); marks `CHECKED_IN`. A second scan shows "Déjà scanné à 20:04 par …".

---

## API surface

All endpoints are under `/api`, JSON, errors in problem+json. Auth via `Authorization: Bearer <Clerk session JWT>`.

**Public**
| Method | Path | |
|---|---|---|
| GET | `/health` | Liveness + DB ping |
| GET | `/venue` | Brand info for the landing |
| GET | `/events?category=&month=` | Published events with next performance and "from" price |
| GET | `/events/{slug}` | Event + performances |
| GET | `/performances/{id}` | Performance detail |
| GET | `/performances/{id}/seatmap` | Layout geometry, zones, seats, prices per zone × tariff available to the caller |
| GET | `/performances/{id}/availability` | Compact seat statuses + standing remaining, with `ETag` (polled every 5 s) |

**Customer** (`ROLE_CUSTOMER`)
| Method | Path | |
|---|---|---|
| GET | `/me` | Current user + roles |
| POST | `/holds` | `{performanceId, seatIds[], standing:[{zoneId, quantity}]}` → hold with `expiresAt` |
| DELETE | `/holds/{id}` | Release |
| POST | `/checkout` | `{holdId, lines:[{performanceSeatId \| zoneId, tariffId}]}` → order + Stripe `clientSecret` |
| GET | `/orders`, `/orders/{reference}` | |
| POST | `/orders/{reference}/cancel` | Cancel + refund within policy |
| GET | `/tickets`, `/tickets/{id}/pdf`, `/orders/{reference}/tickets.pdf` | |
| POST | `/webhooks/stripe` | Public, signature-verified |

**Box office** (`ROLE_BOX_OFFICE`)
| Method | Path | |
|---|---|---|
| GET | `/box-office/performances?date=` | Today's and upcoming performances |
| POST | `/box-office/orders` | `{holdId, lines, paymentMethod: CASH\|CARD_TERMINAL\|NONE, walkIn:{name, email?}, invitationReason?}` |
| POST | `/box-office/orders/{reference}/refund` | |
| GET | `/box-office/refunds/pending` | Counter refunds after a cancellation |
| POST | `/check-in/scan` | `{payload}` → result + ticket summary |

**Organizer Studio** (`ROLE_ORGANIZER`, prefix `/studio`)
| Method | Path | |
|---|---|---|
| CRUD | `/layouts`, `POST /layouts/{id}/clone`, `POST /layouts/generate` | Generator: zones with rows × seats, labels, accessible/blocked seats |
| CRUD | `/events`, `POST /events/{id}/publish` | |
| CRUD | `/performances`, `POST /performances/{id}/open-sales`, `/close-sales`, `/cancel` | Publishing creates inventory rows |
| CRUD | `/tariffs` | |
| PUT | `/performances/{id}/prices` | Full grid zone × tariff; `POST /events/{id}/prices/apply-to-all` |
| GET | `/sales?performance=&channel=&tariff=&from=&to=` | Paginated ticket lines |
| GET | `/exports/sales.csv` | Same filters |
| GET | `/analytics/overview?from=&to=` | KPIs + previous-period deltas |
| GET | `/analytics/fill-rate?from=&to=` | Per performance and per zone |
| GET | `/analytics/revenue-by-tariff`, `/analytics/revenue-by-channel` | |
| GET | `/analytics/sales-timeseries?granularity=day` | |
| GET | `/analytics/booking-curve?event=` | Tickets vs days before show |
| GET | `/analytics/seat-heatmap/{performanceId}` | Sale rank per seat |
| GET | `/analytics/at-risk` | Upcoming performances below expected fill |
| GET/POST | `/integrations/google-sheets`, `/integrations/google-sheets/sync` | Status and manual sync |

Reporting queries use DBAL with explicit SQL and covering indexes. "Revenue" = sum of `unitPriceCents` for tickets `VALID|CHECKED_IN`; refunds are reported separately; invitations are counted in attendance, not revenue.

---

## Frontend

### Routes

| Route | Access | Inspiration |
|---|---|---|
| `/` | Public | Image 1 (grid landing) |
| `/programme` | Public | |
| `/spectacles/:slug` | Public | |
| `/representations/:id/places` | Public (hold requires sign-in) | Image 3 (immersive seat selection) |
| `/commande/:reference/paiement` | Customer | |
| `/commande/:reference/confirmation` | Customer | |
| `/mes-billets` | Customer | |
| `/connexion`, `/inscription` | Public | Clerk components, themed |
| `/guichet` | Box office | |
| `/controle` | Box office | QR scanner |
| `/studio` | Organizer | Image 2 (analytics dashboard) |
| `/studio/spectacles`, `/studio/spectacles/:id`, `/studio/salles`, `/studio/tarifs`, `/studio/ventes`, `/studio/integrations` | Organizer | |
| `/styleguide` | Dev only | Design system showcase |

### Screen notes

**Landing** (from image 1): the page is built on a visible hairline grid. Top row: logo left, nav right. Second row: a search bar split into cells (Spectacle · Mois · Catégorie · button "Voir la programmation") in the ink color. Hero: very large display serif headline over two lines, e.g. *"Le rideau se lève. / La salle vous attend."*, the second line indented after a long rule. A slowly rotating circular text badge ("Saison 26/27 · Théâtre Argane ·") links to the season. Bottom grid row: category links, a poster in an organic wavy SVG mask, a news cell ("Nouvelle configuration concert : la fosse ouvre en janvier"), social links. A partners strip with the invented sponsors closes the page.

**Seat selection** (from image 3, in our sepia night palette): the one bold, memorable moment of the product. On entry, two velvet curtain panels part (≈ 900 ms, once, skipped under reduced motion), the stage at the top glows warm amber. Left column: event card (poster, date, time, duration, tags), performance date pills and time slots, selected tickets list with tariff select, quantity steppers for standing, total, "Payer" button. Right: the SVG hall plan with zoom/pan, zone labels, row letters on both sides, legend (Disponible, Votre sélection, Réservée, Vendue), price tooltip on hover/focus. Seats are keyboard-navigable (arrow keys move within a row, Enter toggles). Hold timer appears as a slim progress bar under the header once seats are held.

**Studio dashboard** (from image 2): light parchment surface, an engraved theater-mask illustration (generated SVG line art) faded behind the page title "Vue d'ensemble". KPI tiles with a dot-matrix sparkline, half-ring gauge for average fill rate with sold / held / remaining legend, bars for daily sales, revenue by tariff, booking curve, fill-by-zone table, seat heatmap. Date range and event filters top right.

**Tickets**: each ticket renders as a stub with perforated notches, the QR on the stub side, seat and tariff in display serif, order reference in tabular figures.

**Empty, loading and error states** are designed, not defaulted: e.g. empty "Mes billets" invites to the programme; `SEAT_UNAVAILABLE` highlights the lost seats on the plan and explains that someone else just took them.

---

## Design system

The direction is **sepia, ceremonial, theatrical**: aged programme paper by day, a dark auditorium with stage light when choosing seats. It borrows the grid discipline of image 1, the editorial serif dashboard of image 2 and the immersive dark plan of image 3, unified in one warm palette. Spend boldness in one place, the seat map; keep everything else quiet.

### Color tokens (`design-system/tokens.css`)

Paper (light surfaces)
| Token | Hex | Use |
|---|---|---|
| `--paper-50` | `#FBF6EC` | Raised surfaces, inputs |
| `--paper-100` | `#F2E8D5` | Page background |
| `--paper-200` | `#E6D7BC` | Alternate sections, table stripes |
| `--paper-300` | `#D3BF9C` | Hairlines, borders |

Sepia and ink
| Token | Hex | Use |
|---|---|---|
| `--sepia-400` | `#B08A5A` | Secondary text on dark, muted icons |
| `--sepia-600` | `#704214` | Primary accent (true sepia): links, active nav, focus ring |
| `--sepia-800` | `#4A2C17` | Hover on accent |
| `--ink-900` | `#2B1A10` | Body text, primary buttons, search bar |

Accents
| Token | Hex | Use |
|---|---|---|
| `--bordeaux-700` | `#6B1E24` | Charts primary series, highlights, "sold out" |
| `--gilt-400` | `#D4A657` | Selected seats, stage glow, ticket foil |
| `--olive-600` | `#5B6B3A` | Success, positive deltas |
| `--rust-700` | `#9A3A26` | Errors, negative deltas |

Night (immersive mode, applied with `data-theme="night"` on the seat selection route)
| Token | Hex |
|---|---|
| `--night-950` | `#140E0A` |
| `--night-900` | `#1E1510` |
| `--night-800` | `#2A1E16` |
| `--night-600` | `#4A3626` |
| `--stage-glow` | `#E9B96E` |

Seat states (night): available `--night-600` fill with `--sepia-400` stroke · selected `--gilt-400` with glow · held by others: hatched `--night-800` · sold `--night-800` at 50 % · accessible: small wheelchair glyph · blocked: hidden.

Contrast: body text ≥ 4.5:1 on both themes; check in the styleguide.

### Typography

| Role | Family | Notes |
|---|---|---|
| Display | **Cormorant Garamond** 500/600 (Google Fonts) | Headlines, event titles, KPI numbers, seat labels on tickets. Tight tracking (−0.01em) at large sizes |
| Text and UI | **Instrument Sans** 400/500/600 | Body, forms, tables, nav. Tabular figures (`font-variant-numeric: tabular-nums`) for prices and counts |

Scale (rem, 1.25 ratio, root 16 px): 0.75 · 0.875 · 1 · 1.25 · 1.563 · 1.953 · 2.441 · 3.815 · 5.96 (hero only). Body line-height 1.55, display 1.05. Line length ≤ 72 ch.
Labels are sentence case. Small caps (`font-variant-caps: all-small-caps`) are reserved for row letters on the seat map and the ticket stub.

### Shape, texture, motion

- Radius scale with hierarchy: 2 px (inputs, table cells), 10 px (cards), 999 px (pills, seat dots). The landing grid uses 0 radius and hairlines.
- Shadows only on floating elements (popovers, ticket stubs): warm `rgba(43,26,16,.14)`.
- A subtle paper grain overlay (inline SVG noise, 4 % opacity) on paper surfaces.
- Posters and imagery get a duotone sepia treatment via an SVG filter so everything shares one color world.
- Motion: one orchestrated moment (the curtain reveal). Elsewhere motion only answers actions: seat toggle spring, ticket added to the list, toast. Durations 160–240 ms, easing `cubic-bezier(.2,.7,.2,1)`. All disabled under `prefers-reduced-motion`.

### Components (primitives)
`Button` (primary ink, secondary outline, ghost, gilt for the main purchase action), `IconButton`, `Input`, `Select`, `DateField`, `Stepper`, `Pill`, `Badge` (status variants), `Tabs`, `Dialog`, `Sheet`, `Toast`, `Table`, `KpiTile`, `DotSparkline`, `HalfGauge`, `TicketStub`, `CountdownBar`, `RotatingBadge`, `WaveMask`, `CurtainReveal`, `EmptyState`, `ColdStartLoader`.

Third-party theming: Clerk `appearance` and Stripe `appearance` (`theme: 'flat'`, variables from our tokens) so sign-in and payment look native.

### Copy rules
Active voice, the button says what happens ("Réserver ces places", "Payer 450 MAD", "Annuler la commande"), the resulting toast uses the same verb ("Commande annulée"). Errors state what happened and what to do ("Ces deux places viennent d'être réservées par quelqu'un d'autre. Choisissez-en d'autres sur le plan.").

---

## Environment variables

**Backend** (`backend/.env` defaults, real values in `.env.local` / Render)
```
APP_ENV=prod
APP_SECRET=
DATABASE_URL=mysql://user:pass@host:port/argane?serverVersion=8.0&charset=utf8mb4
DATABASE_SSL_CA_BASE64=            # Aiven CA cert, base64
CORS_ALLOW_ORIGIN=^https://argane\.vercel\.app$
CLERK_ISSUER=https://<your-instance>.clerk.accounts.dev
CLERK_JWKS_URL=https://<your-instance>.clerk.accounts.dev/.well-known/jwks.json
CLERK_SECRET_KEY=sk_test_...
CLERK_AUTHORIZED_PARTIES=https://argane.vercel.app,http://localhost:5173
ORGANIZER_EMAILS=owner@example.com
BOX_OFFICE_EMAILS=guichet@example.com
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
APP_CURRENCY=mad
HOLD_TTL_SECONDS=600
TICKET_QR_SECRET=                  # falls back to APP_SECRET
SYMFONY_TRUSTED_PROXIES=private_ranges,REMOTE_ADDR   # Render
MAILER_DSN=smtp://mailpit:1025     # prod: a free SMTP provider (e.g. Brevo/Resend), optional
MAILER_FROM="Théâtre Argane <billetterie@example.com>"
GOOGLE_SHEETS_SPREADSHEET_ID=
GOOGLE_SERVICE_ACCOUNT_JSON_BASE64=
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

**Frontend** (`frontend/.env`)
```
VITE_API_URL=http://localhost:8080/api
VITE_CLERK_PUBLISHABLE_KEY=pk_test_...
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
```

If Stripe rejects the configured currency for test PaymentIntents, switch `APP_CURRENCY` to `eur` and note it in `DECISIONS.md`.

---

## Slices and commits

Each bullet is one commit. Tests are written inside the slice that introduces the behavior.

### Slice 0 — Repository bootstrap
```
chore: initialize monorepo with gitignore, editorconfig and license
chore(backend): scaffold Symfony 7.4 skeleton with PHP 8.3
chore(frontend): scaffold Vite React TypeScript app in strict mode
build(docker): add compose with mysql 8, backend, frontend and mailpit
build: add Makefile with up, down, sh, test, lint and fixtures targets
chore(backend): add phpstan, php-cs-fixer and phpunit configuration
chore(frontend): add eslint, prettier and vitest configuration
ci: add GitHub Actions workflow running backend and frontend checks
docs: add README, INSTALL stub, DECISIONS stub and ai-traces folder
```

### Slice 1 — Backend foundations
```
feat(shared): add Money value object with minor units and currency
feat(shared): add UTC clock abstraction and Africa/Casablanca display helper
feat(shared): return problem+json errors with stable error codes
feat(shared): add /api/health endpoint with database ping
chore(backend): configure nelmio cors and api doc bundles
refactor(backend): organize src by module with domain, application, infrastructure and api layers
docs(architecture): describe modules and dependency rules
```

### Slice 2 — Design system
```
feat(design-system): add sepia and night color tokens as CSS variables
feat(design-system): load Cormorant Garamond and Instrument Sans with type scale
chore(frontend): wire Tailwind v4 theme to design tokens
feat(design-system): add Button, IconButton and Badge primitives
feat(design-system): add Input, Select, Stepper and Pill form primitives
feat(design-system): add Dialog, Sheet, Tabs and Toast on Radix
feat(design-system): add paper grain overlay and sepia duotone filter
feat(design-system): add TicketStub, RotatingBadge and WaveMask components
feat(design-system): add KpiTile, DotSparkline and HalfGauge components
feat(frontend): add styleguide route showcasing tokens and components
feat(frontend): add app shell with header, footer and route layouts
```

### Slice 3 — Authentication and roles
```
feat(identity): add User entity with Clerk id, email and roles
feat(identity): verify Clerk session JWTs against cached JWKS
feat(identity): provision users on first request via Clerk backend API
feat(identity): grant organizer and box office roles from env allowlists
feat(identity): add role hierarchy and app:user:grant command
feat(identity): expose GET /api/me
test(identity): cover token validation, expiry, azp and provisioning
feat(auth): add Clerk provider with sepia-themed appearance
feat(auth): add sign-in and sign-up pages
feat(auth): attach session token to API client requests
feat(auth): add role-based route guards and user menu
```

### Slice 4 — Venue and hall layouts
```
feat(venue): add Venue, HallLayout, Zone and Seat entities
feat(venue): add migration for venue schema
feat(venue): add layout generator from zone rows and seat counts
feat(venue): support standing zones with capacity and shape
feat(venue): lock layouts used by performances with sales and add clone
test(venue): cover generator output, labels and layout locking
```

### Slice 5 — Events, performances and inventory
```
feat(catalog): add Event entity with category, status and slug
feat(catalog): add Performance entity with layout and sales window
feat(catalog): add PerformanceSeat inventory and PerformanceZoneStock
feat(catalog): generate inventory when a performance is published
feat(catalog): add migration for catalog and inventory schema
test(catalog): cover publication rules and inventory generation
```

### Slice 6 — Tariffs and price grids
```
feat(pricing): add Tariff entity with channels, roles and quantity limits
feat(pricing): add PerformancePrice grid per zone and tariff
feat(pricing): add TariffEligibilityPolicy for channel, role and quantity
feat(pricing): add migration for pricing schema
test(pricing): cover tariff eligibility matrix
```

### Slice 7 — Booking core: orders and tickets
```
feat(booking): add Hold and StandingHoldLine entities
feat(booking): add Order with reference generator and status machine
feat(booking): add Ticket with price, tariff and seat snapshots
feat(booking): add unique active seat key generated column on tickets
feat(payment): add Payment, Refund and StripeWebhookEvent entities
feat(booking): add migration for booking and payment schema
```

### Slice 8 — Demo data
```
feat(fixtures): seed Théâtre Argane venue with théâtre and concert layouts
feat(fixtures): seed tariffs and season programme with performances
feat(fixtures): seed demo users for each role
feat(fixtures): generate 90 days of realistic orders across channels and tariffs
feat(fixtures): seed one cancelled performance with refunds and one near sold out
feat(frontend): generate typographic SVG posters for demo events
docs(install): document fixtures and demo accounts
```
Fixtures are deterministic (`Faker` seed 2026), use `Payment.provider = FIXTURE`, follow a realistic booking curve (slow start, spike at announcement, rise in the last 10 days) and a channel mix of about 65 % online / 35 % box office.

### Slice 9 — Public catalog
```
feat(catalog): expose published events and event detail endpoints
feat(catalog): expose performance detail with from-price
test(catalog): cover public catalog visibility rules
feat(frontend): generate API types from OpenAPI
feat(landing): build grid hero with display headline and rotating badge
feat(landing): add programme search bar and upcoming performances
feat(landing): add categories, wave-masked poster, news and partners rows
feat(programme): add programme page with category and month filters
feat(event): add event page with performances and prices
feat(frontend): add cold start loader with health retry
```

### Slice 10 — Seat map
```
feat(catalog): expose seatmap geometry with prices for the caller
feat(catalog): expose compact availability with ETag
feat(seatmap): render SVG hall plan with zones, rows and stage
feat(seatmap): add zoom, pan and fit-to-screen controls
feat(seatmap): add selection store with seat limits
feat(seatmap): add keyboard navigation and screen reader labels
feat(seatmap): add standing zone quantity picker
feat(seatmap): add night theme and stage glow
feat(seatmap): add curtain reveal on entry with reduced motion fallback
feat(seatmap): poll availability every 5 seconds and update seat states
feat(seatmap): add event card, date pills, time slots and selection summary
```

### Slice 11 — Holds and concurrency
```
feat(booking): hold seats with a conditional atomic update
feat(booking): hold standing places with locked zone stock
feat(booking): release holds and enforce one active hold per performance
feat(booking): treat expired holds as available everywhere
feat(booking): add scheduler task to expire holds
feat(booking): rate limit hold creation per user
test(booking): prove a seat cannot be held twice under parallel requests
test(booking): cover expiry, release and standing capacity
feat(booking): show hold countdown bar and expiry dialog
feat(booking): highlight seats lost to another buyer on conflict
```
The parallel test spawns two PHP processes (Symfony Process) that try to hold the same seat 50 times; exactly one must win each round.

### Slice 12 — Checkout with Stripe
```
feat(payment): add Stripe gateway behind a PaymentGateway interface
feat(booking): create order from hold with tariff validation and pricing
feat(payment): create PaymentIntent with idempotency key and order metadata
feat(payment): handle Stripe webhooks with signature check and idempotency
feat(booking): finalize sale on payment success
feat(booking): handle failed payments and late success after expiry
test(payment): cover webhook idempotency and late payment refund
build(docker): add stripe-cli service forwarding webhooks locally
feat(checkout): add checkout page with Stripe Payment Element
feat(checkout): theme Stripe appearance with design tokens
feat(checkout): add confirmation page polling order status
```

### Slice 13 — Tickets
```
feat(ticketing): sign QR payloads with HMAC
feat(ticketing): render ticket PDF with QR code and brand layout
feat(ticketing): expose ticket and order PDF downloads
test(ticketing): cover QR signature and tampering
feat(tickets): add Mes billets wallet with ticket stubs
feat(tickets): add order detail with downloads
```

### Slice 14 — Cancellations and refunds
```
feat(booking): cancel customer orders within the 48 hour policy
feat(payment): issue idempotent Stripe refunds
feat(catalog): cancel a performance and stop sales
feat(booking): refund all orders of a cancelled performance asynchronously
feat(booking): flag box office orders for counter refund
test(booking): cover cancellation policy, mass refund and ticket invalidation
feat(tickets): add cancel order flow with confirmation dialog
```

### Slice 15 — Box office
```
feat(box-office): list performances for the counter
feat(box-office): sell with cash, card terminal or invitation
feat(box-office): refund counter orders and list pending refunds
test(box-office): cover restricted tariffs and invitation reasons
feat(guichet): add point of sale screen reusing the seat map
feat(guichet): add tariff assignment and payment method step
feat(guichet): print tickets and start next sale
feat(guichet): add pending counter refunds panel
```

### Slice 16 — Check-in
```
feat(ticketing): validate and check in scanned tickets
test(ticketing): cover double scan, wrong date and cancelled tickets
feat(controle): add camera QR scanner with result feedback
feat(controle): show proof reminder for reduced tariffs
```

### Slice 17 — Organizer Studio (back-office)
```
feat(studio): add studio layout with sidebar navigation
feat(studio): add layout generator form with live plan preview
feat(studio): add events list, create and edit forms
feat(studio): add performance scheduling with layout choice
feat(studio): add publish, open sales, close sales and cancel actions
feat(studio): add tariffs management
feat(studio): add price grid editor with apply to all performances
feat(studio): add sales table with filters
feat(reporting): export sales as CSV
```

### Slice 18 — BI dashboard
```
feat(reporting): add overview KPIs with previous period deltas
feat(reporting): add fill rate per performance and zone
feat(reporting): add revenue by tariff and by channel
feat(reporting): add sales timeseries and booking curve
feat(reporting): add seat heatmap and at-risk performances
perf(reporting): add covering indexes for analytics queries
test(reporting): cover KPI math against fixture data
feat(analytics): add dashboard header with engraved mask art and filters
feat(analytics): add KPI tiles with dot sparklines
feat(analytics): add fill rate gauge and zone breakdown
feat(analytics): add sales, tariff, channel and booking curve charts
feat(analytics): add seat heatmap view
```

### Slice 19 — Google Sheets sync
```
feat(integration): add Google Sheets client with service account
feat(integration): rewrite Ventes and Synthèse tabs idempotently
feat(integration): dispatch sync on sale, refund and cancellation
feat(integration): add scheduled sync and manual sync command
test(integration): cover row mapping with a fake sheets client
feat(studio): add integration status page with sync now
docs(install): document Google Cloud project and sheet sharing
```
Sync strategy: a debounced Messenger message rebuilds both tabs from the database (clear + batch update). At this volume a full rewrite is simpler and always consistent. `Ventes` has one row per ticket (reference, date, event, performance, zone, seat, tariff, channel, price, status); `Synthèse` has fill and revenue per performance.

### Slice 20 — Notifications and live updates
```
feat(notification): send order confirmation email with PDF tickets
feat(notification): send cancellation and refund emails
feat(notification): add branded email templates
feat(seatmap): pause polling when tab is hidden and back off on errors
```
Optional stretch, only if time allows: `feat(seatmap): push availability via Mercure hub in FrankenPHP` (keep polling as fallback).

### Slice 21 — Hardening
```
feat(security): add security headers and strict CORS
feat(security): rate limit checkout and check-in endpoints
fix(a11y): audit focus states, contrast and seat map labels
style(frontend): polish responsive layouts down to 360px
feat(frontend): add designed empty and error states
feat(frontend): add page titles and meta tags
test(e2e): add Playwright purchase smoke test with Stripe test card
```

### Slice 22 — Delivery
```
build(backend): add production Dockerfile on FrankenPHP with supervisord worker
build(backend): decode Aiven CA certificate at boot and enable TLS
build(backend): listen on Render PORT and run migrations on deploy
build: add render.yaml blueprint
build(frontend): add vercel.json with SPA rewrites
docs(install): write full INSTALL guide for local and cloud setup
docs(decisions): finalize answers to the challenge questions
docs: add screenshots and demo walkthrough to README
chore: archive AI traces for submission
```

---

## Challenge coverage

| Challenge level | Requirement | Slices |
|---|---|---|
| 1 — Modéliser | Data model, migrations, realistic demo data, back-office | 4, 5, 6, 7, 8, 17 |
| 2 — Règles | Seat sold once under concurrency, temporary hold, cancellation & refund, automated tests | 11, 12, 14 (tests in each) |
| 3 — Produit | Accounts & roles (client, guichetier, admin), full purchase with seat map, QR ticket, dashboard | 3, 10, 12, 13, 15, 18 |
| 4 — Monde | Google Sheets sync without manual action | 19 |
| 5 — Livrer | Docker environment, INSTALL.md | 0, 22 |
| Questions | Answers in `docs/DECISIONS.md` | see below |

The brief's "client / guichetier / administrateur" map to `ROLE_CUSTOMER`, `ROLE_BOX_OFFICE`, `ROLE_ORGANIZER`.

---

## Decisions to record

Write these in `docs/DECISIONS.md` (in French if submitting to the challenge), each with the choice, the reasoning and the alternatives considered.

1. **Le prix d'un billet déjà vendu change-t-il si le tarif est modifié ?** Non. Le prix, le libellé du tarif et la place sont figés sur le billet au moment de la vente.
2. **Une représentation peut-elle utiliser une configuration de salle différente ?** Oui. Chaque représentation référence une `HallLayout` (théâtre assis ou concert avec fosse debout). Une configuration utilisée par des ventes est verrouillée ; on la clone pour la modifier.
3. **Que devient une place dont le paiement n'aboutit pas ?** Elle reste bloquée jusqu'à l'expiration de la réservation (10 min), puis redevient disponible. Un paiement réussi tardivement reprend la place si elle est libre, sinon il est remboursé automatiquement.
4. **Que se passe-t-il pour les billets si une représentation est annulée ?** Ventes stoppées, billets invalidés, remboursement automatique des paiements en ligne, remboursement au guichet pour les ventes guichet, email aux clients.
5. **Qui a le droit de vendre à quel tarif ?** Voir la matrice canal × rôle × quantité ([Business rules](#who-can-sell-which-tariff)), appliquée côté serveur.

Also record: Clerk vs self-hosted auth, plain controllers vs API Platform, conditional UPDATE vs pessimistic locking vs Redis locks, lazy expiry on free hosting, PaymentIntents vs Stripe Checkout (Checkout sessions cannot expire in under 30 minutes, longer than our hold), full-rewrite Sheets sync, UUID v7 and money in minor units.
