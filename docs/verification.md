# Verification strategy

This repository uses layered verification so business rules are exercised at the
smallest useful boundary and the critical customer path is still proven in a real
browser. Tests must synchronize on observable state; fixed sleeps are prohibited
except for an explicitly bounded lock or timing assertion.

## Automated coverage

| Risk or journey | Automated evidence |
| --- | --- |
| First-run administrator setup and permanent closure | `e2e/critical-journey.spec.ts` and commerce setup feature tests |
| Administrator catalog creation | Playwright creates category, tax, shipping, and product records through the rendered UI; backend feature tests cover update, delete, validation, and audit behavior |
| CSV create/update/upsert, bad rows, unknown categories, stock override, and row limits | Commerce CSV importer feature tests |
| Search, product discovery, cart, quote, and guest checkout | `e2e/critical-journey.spec.ts`, frontend Vitest tests, and commerce API feature tests |
| Successful, declined, errored, duplicate, and late payment callbacks | Commerce webhook and payment API feature tests; the browser compose override makes the successful callback deterministic |
| Atomic multi-line reservation, last-unit oversell prevention, expiry, and retry idempotency | Commerce checkout/expiry feature tests run against MySQL/InnoDB in `mysql-integration` |
| Customer/guest ownership and guest-order account claim | Commerce API feature tests |
| Admin orders, inventory, settings, manual review, and redacted audit records | Commerce admin feature tests |
| OpenAPI operation, error, strict-input, generated-client, and framework-route drift | `npm run openapi:check`; the validator compares both specifications with live Laravel `route:list --json` output |
| Responsive mobile filters and keyboard activation | `e2e/mobile-accessibility.spec.ts` on a Pixel-sized Chromium project |
| WCAG rules detectable by automation | axe scans the desktop storefront and mobile filter interaction for serious or critical WCAG 2.x findings |

Playwright runs with one worker because the journeys intentionally share a fresh
Compose database. Retries are limited to one in CI, and a trace is captured only
on that retry. Screenshots and video are retained only for failures. All accounts,
addresses, and payment numbers are synthetic fixtures; no production credentials
or customer data belong in artifacts.

## Manual WCAG 2.2 AA checklist

Automation cannot prove every accessibility requirement. Before a release that
materially changes the UI, record the browser, operating system, assistive
technology, tester, date, and outcome for each item below:

- Complete setup, catalog administration, search, cart, checkout, and order review
  using only the keyboard. Confirm focus is visible, follows a logical order, is
  not trapped, and returns to the triggering control after dialogs or drawers.
- At 200% and 400% zoom, and at a 320 CSS-pixel viewport, confirm content reflows
  without loss of information or two-dimensional scrolling except for data tables.
- With VoiceOver or NVDA, confirm landmarks and headings communicate page
  structure; fields, errors, status changes, dialogs, tables, and controls have
  useful names, roles, states, and announcements.
- Check normal, hover, focus, disabled, error, and success states with a contrast
  analyzer, including non-text focus indicators and status markers.
- Enable reduced motion, high contrast/forced colors, and dark appearance where
  supported; confirm information is not conveyed by color or motion alone.
- Trigger validation, payment decline, payment error, stale update, stock conflict,
  and session-expiry paths. Confirm errors identify the problem, preserve entered
  data where safe, and provide a clear recovery action.
- Inspect product imagery and icons. Confirm decorative images have empty
  alternatives and informative media has equivalent text.

The following remain manual by design: screen-reader quality, visual focus and
contrast judgment, high zoom/reflow, forced-colors behavior, and cognitive clarity.
Those properties require human perception and cannot be established reliably by
DOM assertions or axe alone.

## Local commands

Run the standard unit, API, contract, lint, type, and build checks:

```sh
./scripts/check.sh
```

Run the deterministic browser stack and journeys:

```sh
cp .env.example .env
docker compose -f compose.yaml -f compose.e2e.yaml up --detach --build --wait
npx playwright install chromium
npm run test:e2e
docker compose -f compose.yaml -f compose.e2e.yaml down --volumes
```
