# Architecture Decision Records

ADRs capture consequential technical choices and their tradeoffs. Business rules and small validation decisions belong in the product/data specifications instead.

| ADR | Decision |
| --- | --- |
| [ADR-001](ADR-001-monorepo-container-topology.md) | Monorepo with independently containerized runtime responsibilities |
| [ADR-002](ADR-002-laravel-react-mysql.md) | Laravel, React, MySQL, and conventional Laravel layers |
| [ADR-003](ADR-003-cookie-authentication.md) | Same-origin SPA with Sanctum cookie authentication |
| [ADR-004](ADR-004-asynchronous-payment-mock.md) | Separate asynchronous payment mock with ephemeral Redis queue |
| [ADR-005](ADR-005-inventory-reservations.md) | Transactional stock reservations and a polling expiration worker |
| [ADR-006](ADR-006-openapi-first-rest.md) | Design-first REST/OpenAPI contract and generated TypeScript client |
| [ADR-007](ADR-007-database-search.md) | MySQL-backed search for the demo |
| [ADR-008](ADR-008-payment-status-polling.md) | Browser polling for asynchronous payment status |

New ADRs should use the next number and include status, context, decision, consequences, and alternatives.
