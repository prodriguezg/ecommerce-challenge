# Commerce API

This Laravel service owns the durable e-commerce domain: setup and
authentication, catalog administration, product CSV import, inventory and
reservations, carts, checkout, orders, payment state transitions, administrative
audit records, and product media.

The service exposes versioned JSON endpoints under `/api/v1`. In the canonical
Docker Compose deployment, Nginx proxies those endpoints through the same origin
as the React application. The OpenAPI contract is maintained in
[`../../openapi/commerce-api.yaml`](../../openapi/commerce-api.yaml), and local
interactive documentation is available at <http://localhost:8080/api/docs> when
`API_DOCS_ENABLED=true`.

## Run the complete application

Use the repository-level workflow rather than starting this service in isolation:

```bash
cp .env.example .env
docker compose up --detach --build --wait
```

The commerce container waits for MySQL, runs migrations and the idempotent
reference seeder, then serves the API. The separate `reservation-worker` service
uses this same image and codebase to expire overdue reservations. Product media
and MySQL data are stored in named Docker volumes.

See the [reviewer and operator guide](../../docs/reviewer-guide.md) for the clean
setup, service addresses, seeded records, reset procedure, configuration, and
reviewer walkthrough.

## Host-native development

The complete host-native workflow is documented in the
[reviewer guide](../../docs/reviewer-guide.md#host-native-setup). Its service
configuration starts from [`.env.example`](.env.example), uses MySQL on
`127.0.0.1:3306`, calls the payment mock on `127.0.0.1:8001`, and serves commerce
on `127.0.0.1:8000`:

```bash
composer install --no-interaction --working-dir=services/commerce-api
cp services/commerce-api/.env.example services/commerce-api/.env
php services/commerce-api/artisan key:generate --no-interaction
php services/commerce-api/artisan migrate --seed --no-interaction
php services/commerce-api/artisan serve --host=127.0.0.1 --port=8000
```

Run `php services/commerce-api/artisan schedule:work` in another terminal so
expired reservations are processed.

## Verification

From the repository root:

```bash
composer test --working-dir=services/commerce-api -- --compact
services/commerce-api/vendor/bin/phpstan analyse --configuration=services/commerce-api/phpstan.neon --memory-limit=1G
services/commerce-api/vendor/bin/pint --test
```

Fast tests use the configured test database; the real-MySQL integration command
and the complete repository check matrix are in the
[reviewer guide](../../docs/reviewer-guide.md#verification-command-matrix).

## Boundaries and limitations

- MySQL is authoritative for commerce data; Redis belongs only to the payment
  mock.
- Payment initiation crosses an HTTP boundary and completion arrives through an
  authenticated, idempotent webhook.
- Email delivery and verification, password recovery, fulfillment, refunds, and
  production observability are outside the demo scope.
- Local credentials, HTTP, and enabled interactive API documentation are not
  production-safe defaults.

The full behavior and production gaps are defined in the
[product requirements](../../docs/product-requirements.md),
[architecture](../../docs/architecture.md), and
[security specification](../../docs/security.md).
