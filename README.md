# E-commerce Code Challenge

This repository contains the product and software-design specifications plus the runnable monorepo foundation for the e-commerce code challenge. Docker Compose starts a React frontend, Laravel commerce API, Laravel mock payment API and queue worker, MySQL, Redis, and a separate reservation worker. Business capabilities are being added incrementally through the linked GitHub issues.

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

## Technology stack

- PHP and Laravel for the commerce and mock payment APIs
- React, TypeScript, Vite, Material UI, React Router, TanStack Query, React Hook Form, and schema validation for the frontend
- The current stable/LTS MySQL release with InnoDB for durable application data
- Redis, with persistence disabled, for the mock payment provider's delayed callback queue
- npm workspaces for JavaScript packages and Composer per Laravel application
- PHPUnit, Vitest, Testing Library, Playwright, OpenAPI validation, Larastan/PHPStan, Laravel Pint, ESLint, and TypeScript checking

The foundation uses Laravel 13 on PHP 8.5, React 19, TypeScript 5.9, Vite 8, MySQL 8.4 LTS, Redis 8.4, Node.js 24, and Nginx 1.28. Application dependencies are pinned by Composer/npm lockfiles; container images use explicit version tags in the Dockerfiles and Compose definition.

## Reviewer workflow

```bash
cp .env.example .env
docker compose up --build
```

Open <http://localhost:8080>. The current foundation page confirms that the stack is running. The commerce startup migrates and runs the application seeder; the current seeder is empty, so no users or products are created. Reference records and first-run setup are delivered by the database and authentication issues.

Service boundaries are intentionally narrow:

- Frontend: <http://localhost:8080>; health: `/health`.
- Commerce API: proxied through `/api`; liveness/readiness: `/api/v1/health/live` and `/api/v1/health/ready`.
- Payment API: internal-only port `8000`; liveness/readiness use the same versioned health paths inside its container.
- MySQL and Redis are internal-only in the canonical Compose stack.

Stop containers while preserving data with `docker compose down`. The following explicit reset is destructive and removes the database and product-media volumes:

```bash
docker compose down --volumes
```

All local defaults live in `.env.example`. Copy it to `.env`, then change reservation, worker, guest-link, payment-delay, CSV, order-number, port, or local credential settings there before starting Compose. These defaults are intentionally unsuitable for production.

## Host-native workflow

Install PHP 8.5, Composer 2.9, Node.js 24, npm 11, and Docker. Start only the infrastructure, bound to loopback:

```bash
cp .env.example .env
docker compose -f compose.yaml -f compose.host.yaml up -d mysql redis
composer install --working-dir=services/commerce-api
composer install --working-dir=services/payment-api
npm install
cp services/commerce-api/.env.example services/commerce-api/.env
cp services/payment-api/.env.example services/payment-api/.env
php services/commerce-api/artisan key:generate
php services/payment-api/artisan key:generate
php services/commerce-api/artisan migrate --seed
```

Run each long-lived process in its own terminal:

```bash
php services/commerce-api/artisan serve --host=127.0.0.1 --port=8000
php services/commerce-api/artisan schedule:work
php services/payment-api/artisan serve --host=127.0.0.1 --port=8001
php services/payment-api/artisan queue:work redis --sleep=1 --tries=3 --timeout=30
npm run dev
```

The reservation schedule and payment behavior are placeholders until their implementation issues land.

## Checks

Install dependencies, then run all currently configured checks:

```bash
./scripts/format.sh
./scripts/check.sh
```

`check.sh` validates both Composer projects, checks PHP formatting, runs both PHPUnit suites, lints/type-checks/tests/builds the npm workspaces, and validates the Compose model.

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
