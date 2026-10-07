# E-commerce Code Challenge

This repository contains a complete local demonstration of the e-commerce code challenge. Docker Compose starts the React storefront/admin application, Laravel commerce API, Laravel mock payment API and queue worker, MySQL, a local phpMyAdmin interface, Redis, and a separate reservation-expiration worker.

## Challenge coverage

| Challenge requirement | Implemented solution |
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
- [Reviewer and operator guide](docs/reviewer-guide.md)
- [Verification coverage and accessibility checklist](docs/verification.md)
- [Requirements traceability](docs/requirements-traceability.md)
- [Security specification](docs/security.md)
- [Architecture decision records](docs/decisions/README.md)

## Example CSV

The challenge-provided example was downloaded on **October 1, 2026**.

- [Original Google Sheet](https://docs.google.com/spreadsheets/d/13kKAvnOmMk6SovPrvOTlvG0oTz6-gUn8BUsxSEoV-UA/edit?gid=262019509)
- [Repository CSV copy](docs/examples/code-challenge-products.csv)

The repository copy is preserved as a sample import fixture and is not imported automatically. It intentionally contains rows that should be rejected by the agreed validation rules. Product images are not supported in CSV imports for this demo.

## Payment test numbers

Only these exact simulation numbers will be accepted:

| Test number | Outcome |
| --- | --- |
| `4000000000010001` | Success after a random 2-8 second delay |
| `4000000000000002` | Decline after a random 2-8 second delay |
| `4000000000090003` | Provider error after a random 2-8 second delay |
| `4000000000080004` | Late success after the configured delay, default 150 seconds |

They are test inputs only. Never enter real payment information.

## Technology stack

- PHP and Laravel for the commerce and mock payment APIs
- React, TypeScript, Vite, Material UI, React Router, TanStack Query, React Hook Form, and schema validation for the frontend
- The current stable/LTS MySQL release with InnoDB for durable application data
- Redis, with persistence disabled, for the mock payment provider's delayed callback queue
- npm workspaces for JavaScript packages and Composer per Laravel application
- PHPUnit, Vitest, Testing Library, Playwright, OpenAPI validation, Larastan/PHPStan, Laravel Pint, ESLint, and TypeScript checking

The foundation uses Laravel 13 on PHP 8.5, React 19, TypeScript 5.9, Vite 8, MySQL 8.4 LTS, Redis 8.4, Node.js 24, and Nginx 1.28. Application dependencies are pinned by Composer/npm lockfiles; container images use explicit version tags in the Dockerfiles and Compose definition.

## Reviewer workflow

Prerequisites are Docker Engine with Compose v2, Git, and free host ports `8080` (the application) and `8081` (phpMyAdmin). From a fresh clone:

```bash
cp .env.example .env
docker compose up --detach --build --wait
```

Open <http://localhost:8080>. A clean database redirects the first visit to `/setup`, where the sole administrator is created. Startup runs migrations and seeds only USD, the 10% `Standard` tax, and the `Ground`/`Air` shipping methods; it never creates users or products. See the [reviewer guide](docs/reviewer-guide.md) for the verified first-run, catalog, CSV, checkout, and manual-review walkthrough.

Service boundaries are intentionally narrow:

- Frontend: <http://localhost:8080>; health: `/health`.
- phpMyAdmin: <http://localhost:8081>; connect with `MYSQL_USER` and `MYSQL_PASSWORD` from `.env` (or the root credentials).
- Commerce API: proxied through `/api`; liveness/readiness: `/api/v1/health/live` and `/api/v1/health/ready`.
- Interactive commerce API documentation: <http://localhost:8080/api/docs> when `API_DOCS_ENABLED=true` (the local default). Disable it in production-like environments.
- Payment API: internal-only port `8000`; liveness/readiness are `/health/live` and `/health/ready` inside its container. Readiness requires Redis and a recent queue-worker heartbeat.
- MySQL and Redis are internal-only in the canonical Compose stack. phpMyAdmin is published on loopback only.

Stop containers while preserving data with `docker compose down`. The following explicit reset is destructive and removes the database and product-media volumes:

```bash
docker compose down --volumes
```

All local defaults live in `.env.example`. Copy it to `.env`, then change reservation, worker, guest-link, payment-delay, CSV, order-number, rate-limit, port, or local credential settings before starting Compose. The complete configuration table is in the [reviewer guide](docs/reviewer-guide.md#configuration-reference). These defaults are intentionally unsuitable for production.

The checked-in environment uses HTTP intentionally and therefore sets `SECURITY_REQUIRE_TLS=false` and `SESSION_SECURE_COOKIE=false`. A production-like deployment must use an HTTPS `APP_URL`, set both values to `true`, retain HTTP-only cookies, provide explicit same-origin CORS configuration, rotate every credential, and set `API_DOCS_ENABLED=false`. The commerce service refuses to boot in required-TLS mode when the URL or cookie flags are unsafe.

## Host-native workflow

The [host-native setup](docs/reviewer-guide.md#host-native-setup) documents exact prerequisites, environment values, migrations, reference seeds, and all five long-running processes. It uses Compose only for loopback-bound MySQL and Redis. Payment provider state and delayed callbacks are intentionally ephemeral: restarting Redis or the payment process can lose idempotency records and pending callbacks.

## Checks

Install dependencies, then run the formatting and complete application check suites:

```bash
./scripts/format.sh
./scripts/check.sh
```

`check.sh` validates both Composer projects, documentation links, PHP formatting/static analysis/tests, frontend lint/type/tests/build, OpenAPI contracts and generated-output drift, and the Compose model. The [verification command matrix](docs/reviewer-guide.md#verification-command-matrix) gives individual commands for formatting, unit/feature/MySQL integration, contract, browser, accessibility, security, container, and build checks.

CI additionally treats any detected secret, any Composer advisory, npm high/critical advisories, and Trivy high/critical repository or container findings as blocking. Unfixed image findings are reported for triage but do not block until an upstream fix exists.

The OpenAPI workflow is also available independently:

```bash
npm run openapi:check
npm run api:generate
npm run api:docs
```

`openapi:check` lints and validates both contracts, verifies their documented endpoint inventories and strict mutation schemas, regenerates the TypeScript client and interactive documentation, and fails on generated-output drift.

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
