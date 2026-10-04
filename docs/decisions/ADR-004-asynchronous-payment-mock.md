# ADR-004: Separate asynchronous payment mock with ephemeral Redis queue

- Status: Accepted
- Date: 2026-10-04

## Context

Payment must be simulated, yet the design should exercise realistic asynchronous state transitions, callback idempotency, reservation expiration, and late success. Ordinary Laravel HTTP processes cannot reliably retain delayed in-memory work after responding.

## Decision

Create a separate Laravel payment API. It accepts exact test card numbers and immediately returns `202 Accepted`, then a supervised queue worker uses Redis to deliver the webhook after a configured delay. Redis persistence is disabled. The provider retries webhook delivery three times with exponential backoff. Webhooks use a shared static bearer token because the provider is a mock. Commerce and provider use idempotency keys/event IDs.

Normal outcomes delay 2-8 seconds by default. Late success defaults to 150 seconds, independently configured from commerce's 120-second reservation timeout. The payment contract does not know reservation expiration.

## Consequences

- Reviewers can see pending, failed, expired, late-success, and manual-review states.
- Provider state and pending callbacks can be lost on restart by design.
- The static token and single multi-process container are not production patterns.
- The independent delay settings must be documented together for reliable demonstrations.

## Alternatives considered

- Synchronous fake payment: simpler but cannot demonstrate the key races.
- In-process PHP memory without a queue: unreliable across requests/processes.
- Durable provider MySQL storage: more realistic but unnecessary for this mock.
- HMAC/mTLS webhook authentication: stronger but beyond the mock's purpose.
- Passing reservation expiry to the provider: deterministic but improperly couples the payment contract to commerce inventory.
