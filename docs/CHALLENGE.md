# Challenge questions

Answers to the five questions of the EMC PFE brief (*Sujet 1 — Billetterie d'une salle de spectacle*).

### 1. Does a sold ticket's price change when the tariff changes?

**No.** At sale time the ticket copies its unit price, tariff label, zone and seat. Later changes to tariffs or price grids only affect new sales. Refunds return what was paid.

### 2. Can a date use a different hall set-up?

**Yes.** Each date picks a set-up: **Théâtre** (398 seats) or **Concert** (standing pit + seats). Its seat inventory is created from that set-up, and prices are set per zone and tariff.

In **Studio → Salle** the organizer switches seats off (broken, technical desk) or back on. Upcoming dates follow where the seat is still free; sold seats are never taken back.

### 3. What happens to a seat whose payment fails?

It stays **held for 10 minutes** (visible countdown), then is free again, with no cron job needed ([decision 8](DECISIONS.md#8-holds-expire-lazily)). A declined card can retry while the hold lasts.

If a payment succeeds **after** the hold expired, the sale goes through if the seats are still free; otherwise the payment is **refunded automatically**. A seat is never sold twice, and no payment is kept without a ticket.

### 4. What happens to tickets when a date is cancelled?

The organizer cancels it in the Studio, with a reason. Then:

1. Sales stop, online and at the counter.
2. Every ticket is voided; check-in refuses it.
3. Online orders are refunded through Stripe.
4. Counter orders are marked *to refund at the counter*.
5. Customers get a cancellation email; the order shows *Remboursée*.

This runs as an idempotent background job (one idempotency key per refund).

Outside a cancellation, a customer can cancel an online order up to **48 h** before the show if no ticket was scanned.

### 5. Who may sell which tariff?

| Tariff | Online | Box office | Organizer |
|---|---|---|---|
| Plein | yes | yes | yes |
| Réduit | yes (proof at entry) | yes | yes |
| Groupe | 10–30 seats | 10–30 seats | yes |
| Invitation (free) | no | yes, reason required | yes |

The rules are data on each tariff (channels, roles, quantities), editable in the Studio, and enforced **on the server** by `TariffEligibilityPolicy`. The UI only shows what the API allows.

Roles: `ROLE_CUSTOMER` < `ROLE_BOX_OFFICE` < `ROLE_ORGANIZER`.
