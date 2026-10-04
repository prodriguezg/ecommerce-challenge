# E-commerce Code Challenge

This repository currently contains the first-pass product and software-design specifications for the e-commerce code challenge. The intended implementation is a Docker Compose-managed monorepo with a React storefront/admin UI, a Laravel commerce API, a Laravel mock payment API, MySQL, Redis for the mock payment queue, and a separate reservation-expiration worker.

## Challenge coverage

| Challenge requirement | Planned solution |
| --- | --- |
| Local database | Current stable/LTS MySQL with InnoDB |
| Product CRUD | Admin-only React UI and versioned REST API |
| CSV product import | Synchronous partial import with selectable create/update/upsert and downloadable rejected-row CSV |
| Product search | MySQL-backed search with filters, sorting, and page-number pagination |
| Product purchase | Cart checkout, inventory reservations, asynchronous simulated payment, and order tracking |
| Required UI | Customer storefront and checkout plus admin catalog/configuration/order screens |
| Docker | Individually containerized frontend, commerce API, payment mock, MySQL, Redis, and reservation worker |
| Decisions and alternatives | Product specification, architecture document, and focused ADRs under `docs/` |
| Local instructions | Target reviewer and host-native workflows below |

## Documentation

- [Product requirements](docs/product-requirements.md)
- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [API conventions and endpoint inventory](docs/api.md)
- [Testing and CI strategy](docs/testing.md)
- [Security specification](docs/security.md)
- [Architecture decision records](docs/decisions/README.md)

## Example CSV

The challenge-provided example was downloaded on **October 1, 2026**.

- [Original Google Sheet](https://docs.google.com/spreadsheets/d/13kKAvnOmMk6SovPrvOTlvG0oTz6-gUn8BUsxSEoV-UA/edit?gid=262019509)
- [Repository CSV copy](docs/examples/code-challenge-products.csv)

The repository copy is preserved as a sample import fixture and is not imported automatically. It intentionally contains rows that should be rejected by the agreed validation rules. Product images are not supported in CSV imports for this demo.

## Planned payment test numbers

Only these exact simulation numbers will be accepted:

| Test number | Outcome |
| --- | --- |
| `4000000000010001` | Success after a random 2-8 second delay |
| `4000000000000002` | Decline after a random 2-8 second delay |
| `4000000000090003` | Provider error after a random 2-8 second delay |
| `4000000000080004` | Late success after the configured delay, default 150 seconds |

They are test inputs only. Never enter real payment information.

## Target technology stack

- PHP and Laravel for the commerce and mock payment APIs
- React, TypeScript, Vite, Material UI, React Router, TanStack Query, React Hook Form, and schema validation for the frontend
- The current stable/LTS MySQL release with InnoDB for durable application data
- Redis, with persistence disabled, for the mock payment provider's delayed callback queue
- npm workspaces for JavaScript packages and Composer per Laravel application
- PHPUnit, Vitest, Testing Library, Playwright, OpenAPI validation, Larastan/PHPStan, Laravel Pint, ESLint, and TypeScript checking

The implementation should use the latest stable major versions available when development begins. Exact dependencies and container images must be pinned through lockfiles and image tags and recorded here when code is introduced.

## Target reviewer workflow

The implementation must make Docker Compose the canonical reviewer workflow. The intended interface is:

```bash
cp .env.example .env
docker compose up --build
```

Startup must migrate and seed only reference data: USD, a 10% Standard tax, Ground shipping at USD 5.00, and Air shipping at USD 15.00. It must not create users or import products. The first browser visit must direct the reviewer to first-run admin setup while no admin exists.

The completed implementation must document:

- Service URLs, ports, and health checks
- How to stop the stack and how to perform an explicit destructive data reset
- How to change reservation timeout, worker interval, guest-link lifetime, payment delays, CSV limits, and order-number prefix/start value
- How to change the four fake-card mappings if the implementation makes them configurable
- How to upload the bundled sample CSV and download rejected rows
- How to run all checks and tests

These commands describe the required end state; this first-pass repository is a specification package and does not yet claim a runnable application.

## Target host-native workflow

The implementation must also support running React and each Laravel application directly on the host while MySQL and Redis may remain containerized. Host-native setup must include dependency installation, environment files, migrations, seed data, API server, frontend dev server, reservation worker, payment API, and payment queue worker instructions.

## Important demo constraints

- Exactly one admin is created through first-run setup; admin and customer capabilities are strictly separate.
- Customer accounts are optional. Guests may check out and may create an account after purchase.
- Email syntax is validated, but email ownership verification, delivery, and password recovery are out of scope.
- The store uses USD only, although monetary records reference a Currency entity to ease future evolution.
- Payment is a simulation. Real card information must never be entered.
- Taxes are exclusive, rounded half-up per line, and configurable through admin-managed tax records.
- Shipping uses fixed admin-managed methods; distance, carrier, dimensions, and weight-based pricing are future work.
- Product search uses MySQL for the demo. Production should evaluate Elasticsearch and Redis caching.
- Observability beyond basic health checks and ordinary logs is out of scope.
- Narrative and AI-attribution comments do not belong in code. Tool-required annotations and type-oriented docblocks are permitted when necessary.

## Production readiness statement

Enterprise concerns influenced the design, but this project must not make unverified scale, latency, or capacity claims. Before production, the solution requires Product decisions identified in the specifications, load and stress tests, penetration testing, a formal security assessment, privacy and retention policies, operational monitoring, backup/recovery planning, and production infrastructure design.

## License

No license is granted. The repository is public for code-challenge review only.
