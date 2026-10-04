# ADR-001: Monorepo with independently containerized runtime responsibilities

- Status: Accepted
- Date: 2026-10-04

## Context

The challenge needs a browser UI, commerce API, local database, asynchronous payment simulation, and expiring inventory reservations. Reviewers need one repository and one straightforward local startup, while runtime responsibilities should remain observable and independently replaceable.

## Decision

Use one monorepo and Docker Compose. Run separate frontend, commerce API, payment mock, MySQL, Redis, and reservation-worker services. Reuse the commerce image/code for the reservation worker. The mock payment API and its queue worker run as supervised processes in one mock-only container.

## Consequences

- One repository keeps the submission easy to review and change atomically.
- Container boundaries expose real integration behavior without splitting the commerce domain into microservices.
- Compose has more services than a single full-stack container.
- The payment multi-process container is not a production scaling model.
- Host-native workflows must also be documented.

## Alternatives considered

- One full-stack container: fewer moving parts but obscures integration and worker boundaries.
- Separate repositories: independent ownership at the cost of review and coordination overhead.
- Commerce microservices: unjustified operational and consistency complexity for this scope.
