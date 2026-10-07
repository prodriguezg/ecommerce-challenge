# Payment Mock API

This Laravel service simulates an external payment provider. It accepts only the
documented test numbers, returns `202 Accepted`, queues a delayed outcome in
Redis, and delivers an authenticated webhook to the commerce API. The delay and
outcome mappings make pending, decline, provider-error, reservation-expiry,
late-success, and manual-review paths reproducible without a real payment
provider.

The service contract is maintained in
[`../../openapi/payment-api.yaml`](../../openapi/payment-api.yaml). In the
canonical Compose stack the API is internal at `payment-api:8000`; it is not
published to the host. Its liveness and readiness endpoints are `/health/live`
and `/health/ready`, and readiness requires Redis plus a recent queue-worker
heartbeat.

## Run the complete application

Use the repository-level workflow:

```bash
cp .env.example .env
docker compose up --detach --build --wait
```

The payment container uses Supervisor to run the Laravel HTTP server and Redis
queue worker together. This is an intentional mock-only simplification. Redis
persistence is disabled, so restarting Redis or the payment service may lose
pending callbacks and mock idempotency state.

See the [reviewer and operator guide](../../docs/reviewer-guide.md) for service
addresses, fake payment numbers, timing controls, and the complete checkout and
manual-review walkthrough.

## Host-native development

The complete host-native workflow is documented in the
[reviewer guide](../../docs/reviewer-guide.md#host-native-setup). Its service
configuration starts from [`.env.example`](.env.example), uses Redis on
`127.0.0.1:6379`, calls commerce on `127.0.0.1:8000`, and serves the mock API on
`127.0.0.1:8001`:

```bash
composer install --no-interaction --working-dir=services/payment-api
cp services/payment-api/.env.example services/payment-api/.env
php services/payment-api/artisan key:generate --no-interaction
php services/payment-api/artisan serve --host=127.0.0.1 --port=8001
```

Run the queue worker in another terminal:

```bash
php services/payment-api/artisan queue:work redis --sleep=1 --tries=4 --timeout=10
```

## Verification

From the repository root:

```bash
composer test --working-dir=services/payment-api -- --compact
services/payment-api/vendor/bin/phpstan analyse --configuration=services/payment-api/phpstan.neon --memory-limit=1G
services/payment-api/vendor/bin/pint --test
```

Contract generation, Compose validation, browser journeys, and the complete check
matrix are documented in the
[reviewer guide](../../docs/reviewer-guide.md#verification-command-matrix).

## Safety and limitations

- The accepted numbers are test identifiers only. Never submit real payment
  information.
- The shared webhook bearer token, ephemeral queue/state, and combined API/worker
  container are explicitly non-production choices.
- A production provider integration requires durable reconciliation, secret
  management, stronger callback authentication, operational monitoring, and
  refund/cancellation workflows.

The design rationale is recorded in
[ADR-004](../../docs/decisions/ADR-004-asynchronous-payment-mock.md), with the
remaining security and operational gaps in the
[security specification](../../docs/security.md).
