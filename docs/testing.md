# Testing and CI Strategy

Exact local and CI-equivalent commands are maintained in the
[verification command matrix](reviewer-guide.md#verification-command-matrix).
Delivered coverage is indexed in
[requirements-traceability.md](requirements-traceability.md#verification-strategy).

## 1. Principles

- Test externally visible behavior and business invariants, not framework internals.
- Use real MySQL for transaction, constraint, locking, and query behavior that SQLite cannot reproduce.
- Keep unit tests fast, but do not mock away the critical database and HTTP boundaries.
- Treat the OpenAPI document as an executable contract.
- Prefer deterministic clocks, random seeds, and fake-provider controls in tests.
- Publish coverage for visibility; do not initially enforce an arbitrary global percentage.

An enforced threshold should be introduced and raised deliberately as the project evolves. Critical workflows require explicit tests regardless of aggregate coverage.

## 2. Backend tests with PHPUnit

### 2.1 Unit tests

- Decimal money operations and half-up rounding
- Per-line tax and shipping-tax calculations
- Currency minor-unit formatting
- Cart merge quantity behavior
- Password and field validation rules
- Order/payment transition guards
- Setting type validation and precedence
- CSV row validation and formula-injection neutralization

### 2.2 Feature/API tests

- First-admin setup availability, successful creation, second attempt, and race protection
- Admin/customer/guest authorization matrix
- Registration/login/logout and CSRF behavior
- Product/category/tax/shipping CRUD and optimistic concurrency conflicts
- Conditional product deletion and permanent SKU reservation
- Tax deletion conflicts and category/shipping deletion side effects
- Image content/size validation and replacement lifecycle
- Search fields, filters, sorting, pagination limits, and deleted-product exclusion
- Cart operations, merge, cap/notification, and deleted/unavailable items
- Checkout validation and current-price change notifications
- Guest token access, expiry, revocation, and post-purchase account claim
- Admin order filters, manual-review resolution note, settings, and audit log

### 2.3 MySQL integration/concurrency tests

- Two checkouts racing for the last units cannot oversell.
- Multi-line reservation is all-or-nothing.
- Admin cannot lower on-hand stock below active reservations.
- Concurrent reservation workers cannot expire one reservation twice.
- Category/tax/shipping active-only uniqueness works under concurrent writes.
- Order sequence generation is unique and respects initial configuration.
- Webhook event uniqueness prevents duplicate stock deduction.
- Deadlock retries, if implemented, do not duplicate business effects.

### 2.4 CSV import tests

- Strict encoding/header/column behavior, including accepted ignored `reason`
- File size and row limits
- Quoted commas/newlines and malformed CSV
- Every in-file duplicate SKU occurrence rejected
- Create/update/upsert matrices
- Soft-deleted SKU rejection
- All three unknown-category policies
- Blank optional clearing and blank required rejection
- Stock override off/on/confirmation and reserved-stock conflict
- Independent valid-row commits and complete rejection reasons
- Safe downloadable file with original columns and reason
- Challenge sample produces expected accepted/rejected categories without relying on a fixed count if the source fixture changes intentionally

## 3. Payment and reservation tests

- Provider initiation accepted, timeout then same-key retry, and terminal initiation failure
- Exact test card mapping
- Normal randomized delay bounded by configuration
- Late-success delay configured independently from reservation timeout
- Decline/error release active reservation
- Expiration worker transitions eligible order/reservations only
- Late success with stock available deducts once and marks paid
- Late success without stock creates manual review
- Duplicate, stale, and out-of-order webhook behavior
- Static bearer token accept/reject
- Three webhook delivery retries and exhausted outcome
- Provider/Redis restart limitation documented and covered where practical
- No card number appears in durable storage or captured logs

## 4. Frontend tests

Use Vitest and Testing Library for:

- Role-based navigation and route guards
- First-run setup states
- Product search/filter/sort/pagination controls
- Product cards/details and tax-exclusive labeling
- Guest and customer cart behavior
- Merge and stock/price adjustment notifications
- Accessible validation summaries and field associations
- Admin CRUD conflict handling
- Import-mode/category/stock confirmation controls and rejection download
- Checkout disclaimer and non-editable fake payment placeholders
- Polling cadence changes and timeout message
- Guest link and post-purchase registration UI
- Manual-review mandatory resolution note

Tests should query by roles/labels and validate keyboard behavior rather than couple to Material UI implementation markup.

## 5. OpenAPI contract tests

- Lint and validate schema syntax.
- Ensure every application route intended as API surface appears in OpenAPI.
- Validate representative requests and every documented success/error response.
- Check Problem Details consistency.
- Verify generated TypeScript client is reproducible and current.
- Detect breaking changes deliberately; require explicit review/versioning decisions.

## 6. End-to-end tests with Playwright

Critical browser journeys:

1. First-run admin creation and setup route closure.
2. Admin catalog/category/tax/shipping creation and image upload.
3. Import challenge CSV, inspect counts, and download rejected rows.
4. Anonymous search, product detail, guest cart, checkout, successful asynchronous payment, and signed order view.
5. Guest post-purchase account creation and order claim.
6. Existing-email guest checkout prompt, login, and alternate-email path.
7. Guest-cart merge into customer cart.
8. Declined and provider-error payment flows.
9. Late success after expiration with both stock-available and manual-review outcomes.
10. Admin manual-review listing and mandatory-note resolution.
11. Core mobile viewport and keyboard-only accessibility smoke paths.

The suite should control provider delay configuration so routine CI remains bounded while retaining at least one faithful late-success integration test.

## 7. Accessibility checks

- Automated axe-style checks on key pages.
- Keyboard traversal and visible-focus assertions.
- Form label, error association, dialog focus, and table semantics tests.
- Contrast checks through theme tooling where feasible.
- Manual WCAG 2.2 AA review checklist for flows automation cannot prove.

Automated checks support but do not certify accessibility.

The executable coverage matrix, artifact policy, and manual WCAG 2.2 AA checklist
are maintained in [verification.md](verification.md).

## 8. Static and build checks

Backend:

- Laravel Pint formatting
- Larastan/PHPStan static analysis
- Composer dependency validation and audit
- Migration checks against MySQL

Frontend:

- ESLint
- Formatting check
- TypeScript no-emit check
- npm dependency audit according to an explicit severity policy
- Production build

Repository:

- Markdown link/style checks
- Mermaid parse/render validation where available
- OpenAPI lint/generation drift check
- Docker/Compose configuration validation
- Secret scanning

## 9. GitHub Actions pipeline

Every pull request runs a thorough pipeline:

1. Repository and dependency validation.
2. Backend formatting/static analysis.
3. Frontend lint/type/component tests/build.
4. OpenAPI lint, route coverage, contract tests, and client drift check.
5. MySQL-backed PHPUnit tests, including focused concurrency cases.
6. Docker image builds and Compose validation.
7. Playwright smoke/critical-flow tests against the Compose stack.
8. Coverage and test artifact publication.

Jobs may run in parallel where dependencies allow. Required checks should be enforced through branch protection once the repository is connected to GitHub.

## 10. Test data and isolation

- Reference seed data is deterministic.
- Test factories create users, products, inventory, orders, payments, and reservations explicitly.
- Tests do not depend on the developer database or uploaded media.
- MySQL state is reset predictably between test scopes.
- Fake time and deterministic provider outcomes avoid sleeps in most tests.
- End-to-end artifacts must not contain secrets or real personal/payment information.

## 11. Performance and production qualification

The initial project makes no numeric performance claim. Before production:

- Define traffic, catalog, order, concurrency, and availability forecasts.
- Establish measurable service-level objectives.
- Run load, stress, soak, and failure/recovery tests.
- Profile database queries and validate indexes with representative data.
- Exercise cache invalidation and search-index consistency if those systems are introduced.
- Validate backup restore and disaster-recovery procedures.
