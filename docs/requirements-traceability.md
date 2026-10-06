# Requirements traceability

This checklist maps the delivered specification to implementation and executable
evidence. A checked item means the demo requirement is implemented or the stated
production gap is explicitly documented; it is not a production certification.

## Product requirements

| Status | Specification | Implementation | Primary evidence |
| --- | --- | --- | --- |
| [x] | [Actors and account rules](product-requirements.md#2-actors) | `AuthenticationController`, `CustomerRegistrationController`, `SetupController`, role middleware/policies, and frontend route guards | Setup, authentication, registration, role-authorization, and Playwright setup tests |
| [x] | [Catalog](product-requirements.md#4-catalog) | Admin product/category/tax/shipping controllers, product image controller, catalog resources, and admin/storefront pages | Admin catalog, product image, catalog API, and browser catalog tests |
| [x] | [Inventory](product-requirements.md#5-inventory) | `InventoryController`, immutable adjustments, reservation floor, and locked inventory mutations | Admin inventory, schema, checkout, and MySQL feature suites |
| [x] | [CSV product import](product-requirements.md#6-csv-product-import) | `ProductCsvImporter`, import controller/resources, rejection download, and admin import UI | `ProductImportControllerTest` covers limits, modes, category policies, duplicates, stock, and safe rejections |
| [x] | [Search and browsing](product-requirements.md#7-search-and-browsing) | `CatalogController`, database filters/sorts/pages, storefront and product pages | Catalog feature tests, frontend tests, and Playwright guest journey |
| [x] | [Cart](product-requirements.md#8-cart) | Browser guest-cart storage, customer cart API, merge/adjustment behavior, and quote endpoint | Cart API feature tests and frontend/browser journeys |
| [x] | [Address, checkout, tax, and totals](product-requirements.md#9-address-and-checkout) | Checkout request/service, immutable snapshots, `Money`, quote UI, idempotency, and half-up calculations | Checkout feature tests, `MoneyTest`, contract checks, and Playwright checkout |
| [x] | [Reservations](product-requirements.md#11-reservations) | Transactional reservation creation, expiration command/scheduler, claim TTL, and stable inventory locking | Checkout and expiration tests plus the real-MySQL feature job |
| [x] | [Simulated payment](product-requirements.md#12-simulated-payment) | Payment mock action/job, Redis queue, webhook authentication/deduplication, and commerce state transitions | Payment API suite, commerce webhook suite, browser checkout, and security tests |
| [x] | [Orders and manual review](product-requirements.md#13-orders) | Customer/guest/admin order resources, private guest tokens, late-success recovery, manual-review service/UI | Order, guest claim, webhook/manual-review, and admin order tests |
| [x] | [Payment-status polling](product-requirements.md#14-payment-status-polling) | `OrdersPage` polls at 2s/5s and pauses at five minutes without state mutation | `OrdersPage.test.ts` and browser checkout |
| [x] | [Admin audit log](product-requirements.md#15-admin-audit-log) | `AdminAuditLogger`, append-only model protection, redaction, API, and admin UI | Admin settings/audit, model, and security-hardening tests |
| [x] | [Accessibility and responsive design](product-requirements.md#16-accessibility-and-responsive-design) | Responsive Material UI layout, semantic controls, keyboard path, labels, focus styles, and axe checks | Desktop/mobile Playwright projects and [manual checklist](verification.md#manual-wcag-22-aa-checklist) |
| [x] | [Seed data](product-requirements.md#17-seed-data) | `ReferenceDataSeeder` creates only USD, Standard tax, Ground, and Air; setup creates the sole admin | Seeder behavior exercised during clean Compose startup and backend suites |
| [x] | [Out of scope and production decisions](product-requirements.md#18-explicitly-out-of-scope) | No hidden production claims; gaps are repeated in README, reviewer guide, architecture, and security gate | Documentation link check and reviewer sign-off |

## Architecture

| Status | Specification | Implementation | Primary evidence |
| --- | --- | --- | --- |
| [x] | [Monorepo layout](architecture.md#2-monorepo-target-layout) | Separate React, commerce, payment, OpenAPI/client, Docker, and docs ownership boundaries | Composer/npm validation and production builds |
| [x] | [Container topology](architecture.md#3-container-topology) | Compose frontend, commerce, payment/worker, reservation worker, MySQL, Redis, and persistent media | Compose validation/builds, health checks, and container CI scans |
| [x] | [Laravel layers](architecture.md#4-laravel-internal-layers) | Routes, Form Requests, policies/middleware, services, models, resources, and commands follow framework boundaries | PHPStan, Pint, PHPUnit, and route/OpenAPI comparison |
| [x] | [Checkout and asynchronous payment](architecture.md#5-major-flow-checkout-and-asynchronous-payment) | Durable order/payment state machines around an idempotent external request and authenticated callback | Checkout, payment, webhook, retry, duplicate, and late-outcome tests |
| [x] | [Reservation expiration](architecture.md#6-major-flow-reservation-expiration) | Scheduled bounded claim and idempotent locked transition using database time | Expiration tests and MySQL integration job |
| [x] | [Partial CSV import](architecture.md#7-major-flow-partial-csv-import) | Whole-file validation with row-level transactions and sanitized result report | CSV matrix feature tests |
| [x] | [Authentication and API contracts](architecture.md#8-authentication-and-browser-boundary) | Same-origin proxy, Sanctum cookie/CSRF flow, API v1, Problem Details, generated TypeScript, optimistic concurrency | Auth/authorization/API tests and OpenAPI drift check |
| [x] | [Data and consistency](architecture.md#10-data-and-consistency-choices) | ULIDs, order sequence, UTC, decimals, snapshots, constraints, transactions, and row locks | Schema/model/value-object tests and MySQL job |
| [x] | [Configuration](architecture.md#11-configuration) | Environment defaults plus audited allowlisted reservation timeout override | Settings controller/service tests and [configuration reference](reviewer-guide.md#configuration-reference) |
| [x] | [Health, evolution, and deployment scope](architecture.md#12-health-and-logs) | Health endpoints and redacted logs are delivered; production evolution/deployment gaps remain explicit | Health/security tests and reviewer guide production-gap list |

## Verification strategy

| Status | Specification | Implementation | Primary evidence |
| --- | --- | --- | --- |
| [x] | [Backend and CSV tests](testing.md#2-backend-tests-with-phpunit) | Commerce PHPUnit unit/feature suites plus a full real-MySQL feature job | `application` and `mysql-integration` CI jobs |
| [x] | [Payment and reservation tests](testing.md#3-payment-and-reservation-tests) | Payment PHPUnit suite and commerce checkout/webhook/expiration suites | `application`, `mysql-integration`, and browser jobs |
| [x] | [Frontend tests](testing.md#4-frontend-tests) | Vitest/Testing Library component and polling tests | `npm run test` in `application` CI |
| [x] | [OpenAPI contract tests](testing.md#5-openapi-contract-tests) | Redocly lint, strict schema checks, live Laravel route comparison, client/HTML regeneration drift | `npm run openapi:check` |
| [x] | [Browser and accessibility tests](testing.md#6-end-to-end-tests-with-playwright) | Deterministic Compose overlay, desktop/mobile projects, critical guest flow, keyboard smoke, and axe | `browser-journeys` CI artifacts and manual WCAG checklist |
| [x] | [Static, build, and CI checks](testing.md#8-static-and-build-checks) | Composer validation/audits, Pint, PHPStan, ESLint, TypeScript, build, docs links, secrets/dependency/fs/image scans | `application`, `security`, and `containers` CI jobs |
| [x] | [Test isolation](testing.md#10-test-data-and-isolation) | SQLite-fast suites, isolated MySQL service, disposable E2E Compose project, factories, deterministic payment override | CI workflow and [local commands](reviewer-guide.md#verification-command-matrix) |
| [x] | [Performance qualification](testing.md#11-performance-and-production-qualification) | No numeric claims; load/stress/soak/recovery work is explicitly pre-production | Reviewer guide and security pre-production gate |

## Security specification

| Status | Specification | Implementation | Primary evidence |
| --- | --- | --- | --- |
| [x] | [Trust boundaries, authentication, authorization](security.md#2-trust-boundaries) | Server-authoritative values, Sanctum/CSRF, role policies, setup closure, hashed guest tokens, and webhook bearer check | Authentication, authorization, guest order, webhook, and hardening tests |
| [x] | [Rate limiting](security.md#5-rate-limiting) | Named, configurable limits on auth, checkout, guest link, uploads, webhook, and provider endpoints | Security-hardening and endpoint feature tests |
| [x] | [Input/output and uploads](security.md#6-input-output-and-injection-controls) | Strict Form Requests, Problem Details, content-based image validation, controlled media, bounded CSV, and formula neutralization | Product image, CSV import, API documentation, and hardening tests |
| [x] | [Payment and concurrency integrity](security.md#8-payment-simulation) | Fake-number allowlist, no card persistence, idempotency, deduplicated callbacks, transaction locks, reservation floors | Payment/checkout/webhook/log-redaction suites and real-MySQL job |
| [x] | [Secrets and browser controls](security.md#10-secrets-and-environment) | Example-only secrets, startup TLS guard, cookie flags, same-origin CORS, CSP and security headers | Gitleaks/Trivy, dependency audits, and security-hardening tests |
| [x] | [Logging, audit, and supply chain](security.md#12-logging-audit-and-personal-data) | Sensitive-data redaction, immutable audit records, lockfiles, pinned runtime images/actions, and scans | Unit/feature security tests plus `security`/`containers` CI jobs |
| [x] | [Operational gaps and pre-production gate](security.md#14-availability-and-operational-gaps) | Missing production controls are explicitly documented and are not represented as delivered | [Reviewer limitations](reviewer-guide.md#demo-limitations-and-production-gaps) and security gate |

## Final acceptance checklist

- [x] Fresh-clone Compose commands, URLs, health endpoints, seed contents, stop,
  and destructive reset are documented.
- [x] Host-native setup covers both Laravel services, React, MySQL, Redis,
  migrations, reference seeds, queue work, and reservation scheduling.
- [x] Configuration names/defaults match `.env.example`, service examples, and
  the implemented runtime override.
- [x] Reviewer steps cover first admin, catalog, sample CSV/rejections, checkout
  outcomes, and manual review.
- [x] Individual and CI-equivalent verification commands are documented.
- [x] Demo limitations, role separation, destructive operations, and production
  gaps are explicit.
- [x] Local documentation links are enforced by `npm run docs:check`.
