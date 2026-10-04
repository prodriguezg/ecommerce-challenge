# ADR-005: Transactional stock reservations and a polling expiration worker

- Status: Accepted
- Date: 2026-10-04

## Context

Asynchronous payment creates a period when stock must be held without being permanently deducted. Abandoned or failed payments must release stock, and concurrent checkouts must not oversell.

## Decision

Store on-hand inventory separately from active reservation rows. Reserve the entire cart in one MySQL transaction using row-level locks. Give every reservation a fixed expiration timestamp based on an active configurable timeout, default 120 seconds. Run a separate Laravel worker that polls every five seconds by default, claims overdue rows atomically, and expires them idempotently using database UTC time.

Successful payment consumes the reservation and deducts on-hand stock. Failure releases it. Late success attempts a fresh atomic stock deduction and routes insufficient stock to manual review.

## Consequences

- Temporary holds do not repeatedly mutate on-hand stock.
- Availability remains correct under concurrency when locks and transitions are implemented correctly.
- Polling introduces bounded expiration lag and database work.
- Multiple workers require safe claim behavior and MySQL integration tests.
- Late success has an explicit exception workflow rather than overselling silently.

## Alternatives considered

- Deduct only at payment success: smallest model but stock can be sold to competing checkouts during payment.
- Reserve when added to cart: abandoned carts lock inventory too early.
- Per-reservation delayed queue job: precise but adds durable queue infrastructure to commerce.
- Database-native scheduler: couples application behavior more tightly to database operations.
