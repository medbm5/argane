# Architecture

Théâtre Argane is a **modular monolith**: one Symfony 7.4 deployable split into modules by business capability, plus a React SPA.

```
Vercel (React SPA) ──HTTPS──▶ Render (FrankenPHP + Messenger worker, one container)
                                   ├──▶ Aiven MySQL 8 (TLS)
                                   ├──▶ Stripe (PaymentIntents, webhooks)
                                   ├──▶ Clerk (JWKS, Backend API)
                                   └──▶ Google Sheets API
```

## Backend modules

| Module | Owns |
|---|---|
| `Shared` | `Money`, `VenueTime`, problem+json errors, health check, pagination |
| `Identity` | `User`, Clerk JWT authenticator, roles, JIT provisioning |
| `Venue` | `Venue`, `HallLayout`, `Zone`, `Seat`, layout generator |
| `Catalog` | `Event`, `Performance`, inventory (`PerformanceSeat`, `PerformanceZoneStock`) |
| `Pricing` | `Tariff`, `PerformancePrice`, `TariffEligibilityPolicy` |
| `Booking` | `Hold`, `Order`, `Ticket`, seat locking, cancellations |
| `Payment` | `PaymentGateway` (Stripe), webhooks, `Payment`, `Refund` |
| `Ticketing` | QR signing, PDF rendering, check-in |
| `Reporting` | Read-only analytics (DBAL), CSV exports |
| `Integration` | Google Sheets sync |
| `Notification` | Emails |

### Layers inside a module

```
<Module>/
├── Domain/          Entities (Domain/Model), value objects, enums, domain events, repository interfaces
├── Application/     Use cases (Command + Handler), queries, DTOs, policies
├── Infrastructure/  Doctrine repositories, external clients
└── Api/             Controllers, request/response DTOs, voters
```

### Dependency rules

Checked by `tests/Unit/Architecture/ModuleBoundariesTest.php`:

1. A module may use another module's **Domain** and **Application** layers, never its **Infrastructure** or **Api**.
2. **Domain** code stays framework-free: no HttpFoundation, no bundles, no DBAL connection. Doctrine ORM mapping attributes are tolerated on entities as a pragmatic exception.
3. Cross-module side effects (e.g. "a sale happened → sync Google Sheets, send email") go through domain events dispatched on the Messenger bus, not direct calls.
4. `Reporting` reads other modules' tables with explicit SQL; it never writes.

## Cross-cutting conventions

- **Errors**: every `/api` error is `application/problem+json` with a stable `code` (`SEAT_UNAVAILABLE`, `HOLD_EXPIRED`, `VALIDATION_FAILED`, …), rendered by `Shared\Api\ProblemJsonListener`. Business rules throw `Shared\Domain\Exception\DomainException` subclasses.
- **Money**: integer minor units + currency (`Money`), never floats.
- **Time**: stored in UTC (the kernel forces it), displayed in `Africa/Casablanca` (`VenueTime`). Services depend on PSR-20 `ClockInterface`; tests use `MockClock`.
- **IDs**: UUID v7; public order references `ARG-26-000123`.
- **HTTP**: plain controllers with `#[MapRequestPayload]` DTOs and the Validator. OpenAPI at `/api/doc.json`.
- **Async**: Symfony Messenger on the Doctrine transport; Scheduler for periodic jobs. Correctness never depends on the worker being awake (holds expire lazily).

## Frontend

React 19 + Vite + TypeScript strict. Features live in `src/features/<feature>`, routes in `src/pages`, design tokens and primitives in `src/design-system`. Server state with TanStack Query, client state with Zustand. See the README for the full table.
