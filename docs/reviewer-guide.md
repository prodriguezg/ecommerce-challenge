# Reviewer and operator guide

This guide is the canonical runbook for evaluating the demo. Commands are run
from the repository root unless a command says otherwise. Local defaults are
for an isolated development machine only.

## Clean Docker Compose setup

### Prerequisites

- Git
- Docker Engine with Compose v2
- Free host port `8080`
- At least 6 GB of free disk space for images, dependencies, and test artifacts

From a fresh clone:

```bash
cp .env.example .env
docker compose up --detach --build --wait
docker compose ps
```

The first build downloads the pinned images and application dependencies. The
commerce entrypoint waits for MySQL, runs migrations, and runs the idempotent
reference seeder before reporting ready. The payment readiness endpoint remains
unhealthy until its Redis-backed queue worker has recorded a recent heartbeat.

Open <http://localhost:8080>. A clean database redirects to `/setup`; it does
not contain a user or product. Startup creates only these reference records:

- USD as the base currency
- `Standard` tax at 10%
- `Ground` shipping at USD 5 and `Air` shipping at USD 15

Check the public endpoints:

```bash
curl --fail http://localhost:8080/health
curl --fail http://localhost:8080/api/v1/health/live
curl --fail http://localhost:8080/api/v1/health/ready
```

### Service addresses

| Surface | Compose address | Health/readiness |
| --- | --- | --- |
| React storefront and admin | <http://localhost:8080> | <http://localhost:8080/health> |
| Commerce API | Same origin under `/api/v1`; internal `commerce-api:8000` | `/api/v1/health/live`, `/api/v1/health/ready` |
| Interactive commerce API docs | <http://localhost:8080/api/docs> | Available only when `API_DOCS_ENABLED=true` |
| Payment mock | Internal `payment-api:8000`; deliberately not published | `/health/live`, `/health/ready` inside the container |
| MySQL | Internal `mysql:3306` | Compose health check |
| Redis | Internal `redis:6379` | Compose health check |

The canonical stack exposes only the frontend. Use `compose.host.yaml` only for
host-native development; it binds MySQL and Redis to loopback.

### Stop, inspect, and reset

```bash
docker compose logs --follow
docker compose down
```

`docker compose down` preserves the MySQL and product-media volumes. The next
command is explicitly destructive: it permanently deletes the local database,
all accounts/orders/catalog data, and uploaded product media for this Compose
project.

```bash
docker compose down --volumes
```

Never use the destructive reset against a project name or Docker context that
contains data you need.

## Host-native setup

Install PHP 8.5 with PDO MySQL, Composer 2.9, Node.js 24.13.1 or newer in the
24.x line, npm 11.8 or newer, and Docker Compose. The applications run on the
host; Compose supplies loopback-only MySQL and Redis.

```bash
cp .env.example .env
docker compose -f compose.yaml -f compose.host.yaml up --detach mysql redis
composer install --no-interaction --working-dir=services/commerce-api
composer install --no-interaction --working-dir=services/payment-api
npm ci
cp services/commerce-api/.env.example services/commerce-api/.env
cp services/payment-api/.env.example services/payment-api/.env
php services/commerce-api/artisan key:generate --no-interaction
php services/payment-api/artisan key:generate --no-interaction
php services/commerce-api/artisan migrate --seed --no-interaction
```

The checked-in service examples are already aligned for this topology:

- commerce `APP_URL=http://localhost:5173`
- commerce `PAYMENT_API_URL=http://127.0.0.1:8001/api/v1/payments`
- both callback settings point to
  `http://127.0.0.1:8000/api/v1/payments/webhooks/mock`
- both services use the same local-only `PAYMENT_WEBHOOK_TOKEN`
- commerce uses MySQL on `127.0.0.1:3306`; payment queues/cache use Redis on
  `127.0.0.1:6379`

Run each long-lived command in its own terminal:

```bash
php services/commerce-api/artisan serve --host=127.0.0.1 --port=8000
php services/commerce-api/artisan schedule:work
php services/payment-api/artisan serve --host=127.0.0.1 --port=8001
php services/payment-api/artisan queue:work redis --sleep=1 --tries=4 --timeout=10
npm run dev
```

Open <http://localhost:5173>. Direct host-native service checks are:

```bash
curl --fail http://127.0.0.1:8000/api/v1/health/ready
curl --fail http://127.0.0.1:8001/health/ready
```

Stop the five processes with `Ctrl-C`, then preserve infrastructure data with:

```bash
docker compose -f compose.yaml -f compose.host.yaml down
```

## Configuration reference

Compose reads `.env`; host-native applications read their service-level `.env`
files. Restart affected services after environment changes. The admin Settings
screen can override only `RESERVATION_TIMEOUT_SECONDS`; the override applies to
new reservations and can be removed to return to the environment value.

| Setting | Local default | Meaning |
| --- | ---: | --- |
| `FRONTEND_PORT` | `8080` | Published Compose application port |
| `MYSQL_HOST_PORT`, `REDIS_HOST_PORT` | `3306`, `6379` | Loopback ports used only with `compose.host.yaml` |
| `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | local-only values | MySQL bootstrap credentials; replace outside local use |
| `COMMERCE_APP_KEY`, `PAYMENT_APP_KEY` | deterministic local-only keys | Laravel encryption keys; generate unique keys outside this demo |
| `PAYMENT_WEBHOOK_TOKEN` | local-only token | Shared bearer token for mock callbacks; never expose it to the browser |
| `SECURITY_REQUIRE_TLS` | `false` | Reject unsafe boot configuration when enabled |
| `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` | `false`, `true`, `lax` | Local HTTP cookie policy; secure cookies and TLS are mandatory outside local use |
| `RESERVATION_TIMEOUT_SECONDS` | `120` | Lifetime of newly created reservations; admin override range is 30–3600 seconds |
| `RESERVATION_WORKER_INTERVAL_SECONDS` | `5` | Reservation-expiration scheduler cadence |
| `RESERVATION_EXPIRATION_BATCH_SIZE` | `100` | Maximum reservations claimed per expiration pass |
| `RESERVATION_CLAIM_TTL_SECONDS` | `60` | Stale worker-claim recovery window |
| `GUEST_ORDER_LINK_DAYS` | `30` | Private guest-order link lifetime |
| `PAYMENT_NORMAL_DELAY_MIN_SECONDS`, `PAYMENT_NORMAL_DELAY_MAX_SECONDS` | `2`, `8` | Inclusive simulated delay range for normal outcomes |
| `PAYMENT_LATE_SUCCESS_DELAY_SECONDS` | `150` | Delay for the late-success test number |
| `PAYMENT_CONNECT_TIMEOUT_SECONDS`, `PAYMENT_TIMEOUT_SECONDS` | `2`, `5` | Commerce-to-provider connect and total request timeouts |
| `PAYMENT_WEBHOOK_CONNECT_TIMEOUT_SECONDS`, `PAYMENT_WEBHOOK_TIMEOUT_SECONDS` | `2`, `5` | Provider callback connect and total request timeouts |
| `PAYMENT_WORKER_HEARTBEAT_TTL_SECONDS` | `10` | Maximum payment-worker heartbeat age accepted by readiness |
| `CSV_MAX_BYTES`, `CSV_MAX_ROWS` | `5242880`, `10000` | Whole-file CSV limits |
| `ORDER_NUMBER_PREFIX`, `ORDER_NUMBER_START` | `ORD-`, `100001` | Display number prefix and first sequence value; existing numbers never change |
| `API_DOCS_ENABLED` | `true` | Enables local `/api/docs`; disable in production-like environments |

Rate-limit variables are requests per minute: `AUTH_LOGIN_RATE_LIMIT=5`,
`AUTH_SETUP_RATE_LIMIT=3`, `AUTH_CUSTOMER_REGISTRATION_RATE_LIMIT=5`,
`AUTH_GUEST_REGISTRATION_RATE_LIMIT=5`, `CHECKOUT_RATE_LIMIT=30`,
`IMAGE_UPLOAD_RATE_LIMIT=10`, `CSV_UPLOAD_RATE_LIMIT=5`,
`GUEST_ORDER_RATE_LIMIT=60`, `PAYMENT_REQUEST_RATE_LIMIT=60`, and
`PAYMENT_WEBHOOK_RATE_LIMIT=120`.

Browser order-status polling is intentionally code-defined rather than an
operator setting: every two seconds for 30 seconds, every five seconds
thereafter, and paused after five minutes. Pausing the browser never cancels or
changes the order.

### Fake payment mapping

| Exact test number | Result |
| --- | --- |
| `4000000000010001` | Success after the normal delay |
| `4000000000000002` | Decline after the normal delay |
| `4000000000090003` | Provider error after the normal delay |
| `4000000000080004` | Success after the independent late delay |

These are identifiers for a fake provider, not real card numbers. Never enter
real payment information.

## Reviewer walkthrough

### 1. Create the sole administrator

1. Start with a clean database and open <http://localhost:8080>.
2. The application redirects to `/setup`. Enter an administrator name, email,
   and a password of at least ten characters containing uppercase, number, and
   symbol characters.
3. Submit once. The browser enters `/admin/orders`; revisiting `/setup` now
   redirects to login. There is no second-admin workflow.

An administrator cannot shop. Use **Log out** and a separate private browser
window for customer/guest checks so the roles remain visibly separate.

### 2. Import the challenge CSV and download rejections

1. As the administrator, open **Imports**.
2. Choose `docs/examples/code-challenge-products.csv`.
3. Keep **Create only**. Choose **Create missing categories** if you want valid rows to
   populate the catalog; **Reject row** deliberately rejects unknown categories.
4. Leave stock override off for the first import and submit.
5. Review accepted/warning/rejected counts. The sample intentionally includes
   invalid values such as `free` and malformed numbers; a zero-rejection result is not
   expected.
6. Select **Download rejected rows**. The CSV preserves the original columns,
   adds `reason`, and neutralizes spreadsheet-formula prefixes.

The file is UTF-8 and accepts only `name`, `sku`, `description`, `category`,
`price`, `stock`, `weight_kg`, plus an ignored `reason` column. Valid rows commit
independently. Numeric cells accept surrounding whitespace and valid thousands
commas; cells containing commas must be quoted. `price` accepts integers or
1–2 decimal places and an optional leading `$` (including `$ 12.50`).
`weight_kg` accepts integers or 1–4 decimal places; `stock` accepts integers
only. Neither weight nor stock accepts `$`. Values remain non-negative and
within their database ranges, including the signed 32-bit audit delta when
stock changes (new-product stock is at most `2147483647`). Malformed grouping,
empty values, signs, exponents, and excess precision reject only the affected row with exact
`Invalid price value`, `Invalid weight_kg value`, or `Invalid stock value`
reasons, aggregated once each. See the [full numeric import contract](product-requirements.md#61-file-contract).

### 3. Set up and inspect the catalog

1. **Categories**, **Taxes**, and **Shipping** show reference records and allow
   additional active records.
2. In **Products**, select **Add product** and provide a unique SKU, price,
   optional category/tax IDs, and initial stock. Imported products use the
   built-in placeholder image.
3. Select **Inventory** from a product row. Record a stock adjustment using an
   allowed reason; `other` requires a note. Stock cannot be lowered below active
   reservations.
4. Open the storefront in a logged-out/private window and exercise search,
   filters, sorting, the product page, and cart.

### 4. Exercise checkout outcomes

Add an in-stock product to the guest cart, open checkout, enter a shipping
address, select a seeded shipping method, and use one fake number from the table
above. Success consumes the reservation and displays a private guest order page;
decline and provider error release it. The page polls automatically. After a
successful guest checkout it also offers optional account creation, which claims
the order and revokes the private guest token.

### 5. Produce and resolve a manual review

This scenario intentionally manipulates timing and stock. Use disposable local
data, set these `.env` values before startup, and keep one product at stock `1`:

```dotenv
RESERVATION_WORKER_INTERVAL_SECONDS=1
PAYMENT_NORMAL_DELAY_MIN_SECONDS=0
PAYMENT_NORMAL_DELAY_MAX_SECONDS=0
PAYMENT_LATE_SUCCESS_DELAY_SECONDS=45
```

1. In **Admin → Settings**, save a reservation timeout of `30` seconds.
2. As guest A, buy the single unit with late-success number
   `4000000000080004`.
3. After the reservation expires, as guest B buy the now-available unit with
   success number `4000000000010001`.
4. When guest A's callback arrives, no stock remains, so A moves to
   `manual_review` without double-deducting inventory.
5. As admin, open **Manual reviews**, select A's order, enter the mandatory
   resolution note, and choose **Mark processed**.

Marking a review processed records an audited external resolution only. It does
not refund, cancel, fulfill, or allocate stock.

## Verification command matrix

Install dependencies first with the three install commands from the host-native
setup. Copy `.env.example` to `.env` for Compose validation.

| Area | Command |
| --- | --- |
| PHP formatting (write) | `./scripts/format.sh` |
| Composer manifests | `composer validate --strict --working-dir=services/commerce-api` and the same command for `services/payment-api` |
| PHP formatting (check) | `services/commerce-api/vendor/bin/pint --test` and the payment equivalent |
| PHP static analysis | `services/commerce-api/vendor/bin/phpstan analyse --configuration=services/commerce-api/phpstan.neon --memory-limit=1G` and the payment equivalent |
| Commerce unit/feature tests | `composer test --working-dir=services/commerce-api -- --compact` |
| Payment unit/feature tests | `composer test --working-dir=services/payment-api -- --compact` |
| Documentation links | `npm run docs:check` |
| OpenAPI lint/contracts/generated drift | `npm run openapi:check` |
| Regenerate client and API HTML | `npm run api:generate` |
| Frontend/repository lint | `npm run lint` |
| TypeScript | `npm run typecheck` |
| Vitest | `npm run test` |
| Production builds | `npm run build` |
| Compose model | `docker compose --env-file .env.example config --quiet` |
| Complete non-container suite | `./scripts/check.sh` |

### MySQL integration and concurrency suite

Use an isolated Compose project and database so tests cannot erase reviewer data:

```bash
export COMPOSE_PROJECT_NAME=ecommerce-mysql-tests
export MYSQL_DATABASE=ecommerce_test
export MYSQL_USER=ecommerce_test
export MYSQL_PASSWORD=ecommerce_test
export MYSQL_ROOT_PASSWORD=root_test
export MYSQL_HOST_PORT=3307
docker compose -f compose.yaml -f compose.host.yaml up --detach --wait mysql
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=ecommerce_test DB_USERNAME=ecommerce_test DB_PASSWORD=ecommerce_test services/commerce-api/vendor/bin/phpunit --configuration services/commerce-api/phpunit.xml --testsuite Feature
docker compose -f compose.yaml -f compose.host.yaml down --volumes
```

The final `down --volumes` is destructive only to the explicitly named
`ecommerce-mysql-tests` project.

### Browser and automated accessibility suite

```bash
export COMPOSE_PROJECT_NAME=ecommerce-e2e
export FRONTEND_PORT=8081
docker compose -f compose.yaml -f compose.e2e.yaml up --detach --build --wait
E2E_BASE_URL=http://localhost:8081 npm run test:e2e
docker compose -f compose.yaml -f compose.e2e.yaml down --volumes
```

Playwright covers desktop/mobile critical journeys, keyboard behavior, and
serious/critical axe findings. Failures retain bounded screenshots, traces, and
HTML results under `test-results/` and `playwright-report/`; CI retains the same
artifacts for seven days. Automated checks do not certify WCAG conformance.
Complete the [manual WCAG 2.2 AA checklist](verification.md#manual-wcag-22-aa-checklist).

### Security and container checks

Application dependency checks:

```bash
composer audit --locked --abandoned=fail --working-dir=services/commerce-api
composer audit --locked --abandoned=fail --working-dir=services/payment-api
npm audit --audit-level=high
```

With Gitleaks and Trivy installed, the local equivalents of the blocking scans
are:

```bash
gitleaks detect --source . --redact
trivy fs --scanners vuln,misconfig,secret --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 .
docker compose build
trivy image --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 ecommerce-challenge-frontend:latest
trivy image --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 ecommerce-challenge-commerce-api:latest
trivy image --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 ecommerce-challenge-payment-api:latest
```

CI pins the scanner actions and is authoritative for the delivered versions.
Unfixed image vulnerabilities remain visible for triage but do not block; fixed
high/critical findings, secrets, Composer advisories, and npm high/critical
advisories block.

## Demo limitations and production gaps

- The sole admin and customer/guest capabilities are intentionally separate.
- Email is neither delivered nor ownership-verified; password recovery is absent.
- Payment is a simulation with ephemeral Redis state and no refund/cancel flow.
- CSV work is synchronous; large production imports need durable background jobs.
- Search is MySQL-backed; relevance, typo tolerance, and cache invalidation are
  not production-qualified.
- Product media lacks malware scanning, re-encoding, object storage, and lifecycle
  automation.
- The stack has health checks and logs but no metrics, traces, alerting, backups,
  restore rehearsal, high availability, or deployment/rollback automation.
- No load, stress, soak, disaster-recovery, penetration, privacy, retention, or
  compliance qualification has been completed.
- Local HTTP, known example credentials, enabled API docs, and the static webhook
  bearer token are not production-safe.

The complete unresolved production gate is maintained in
[security.md](security.md#15-pre-production-security-gate) and the implementation
evidence is indexed in [requirements-traceability.md](requirements-traceability.md).
