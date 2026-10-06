# Security Specification

The [reviewer guide](reviewer-guide.md#demo-limitations-and-production-gaps)
separates delivered demo controls from production gaps. Implementation and test
evidence is indexed in
[requirements-traceability.md](requirements-traceability.md#security-specification).

## 1. Scope

This document defines the demo's minimum security controls and records production gaps. It is not a penetration-test report, compliance attestation, or complete threat model. Penetration testing and a formal security assessment are required before production.

## 2. Trust boundaries

- The browser and all local-storage cart data are untrusted.
- The frontend proxy does not replace API authorization.
- The commerce API is authoritative for identity, roles, prices, tax, stock, totals, orders, and configuration.
- The payment mock is an external-service simulation even though Compose runs it locally.
- Webhook input is untrusted until bearer authentication, schema validation, event deduplication, and state checks pass.
- Uploaded images and CSV files are untrusted content.
- MySQL and Redis are not exposed publicly in the target Compose setup.

## 3. Authentication and sessions

- Laravel Sanctum secure HTTP-only cookies.
- CSRF protection on state-changing browser requests.
- SameSite policy appropriate to the single-origin deployment.
- Secure cookie flag in TLS environments.
- Session fixation protection on login and privilege changes.
- Logout invalidates the current session.
- Passwords are hashed with Laravel's current secure default and never logged.
- Password policy: minimum ten characters, at least one uppercase letter, one number, and one symbol.
- Login and setup responses avoid unnecessary account enumeration.

Email syntax and uniqueness are enforced, but email ownership is not verified. Email verification and password recovery are production requirements.

## 4. Authorization

- API policies enforce permissions regardless of UI visibility.
- Admin and customer roles are strictly separate.
- Only setup can create the sole admin; it is enabled only while no admin exists.
- Transactional/database enforcement prevents concurrent setup from creating multiple admins.
- Customers may access only their own cart and orders.
- Guest order access requires an unguessable token whose hash, not plaintext, is stored.
- Guest links expire by configuration and are revoked when an account claims the order.
- Admin order inspection and audit data are never exposed to customer routes.

## 5. Rate limiting

Apply endpoint-specific limits to:

- Login
- Direct and post-purchase registration
- First-admin setup
- Guest-order token access
- Checkout/idempotency abuse
- Image and CSV upload
- Webhook endpoint

Limits must be configurable and tested. Production limits require traffic evidence and abuse monitoring rather than copied demo values.

## 6. Input, output, and injection controls

- Laravel validation/Form Requests define allowed shapes and reject unknown mutation fields where practical.
- Eloquent/query builder parameterization is used; raw SQL requires explicit review and bound parameters.
- React renders untrusted catalog/customer text as text, not HTML.
- Problem responses never include stack traces, SQL, internal paths, or secrets.
- User-controlled values are safely encoded in HTML, JSON, CSV, URLs, and logs according to context.
- Downloaded rejection CSV neutralizes spreadsheet-formula prefixes such as `=`, `+`, `-`, and `@` while preserving an understandable correction workflow.
- Country codes, enums, ULIDs, decimals, quantities, and timestamps have explicit schema validation.

The challenge sample includes adversarial-looking values; they must display as inert text and exercise validation safely.

## 7. Upload security

### 7.1 Images

- Maximum 5 MB.
- JPEG, PNG, and WebP only.
- Validate actual content/type, not extension or client header alone.
- Generate stored filenames; never use a user path.
- Store outside executable application code and serve through a controlled media path.
- Apply `X-Content-Type-Options: nosniff` and correct content type.
- Replace atomically and remove old files only after successful persistence.
- Clean orphaned uploads safely.

Production should add malware scanning, image decoding/re-encoding, dimension limits, object storage, signed delivery, and lifecycle policies.

### 7.2 CSV

- Enforce byte/row limits before costly processing.
- Require UTF-8 and a strict header allowlist.
- Bound field lengths and parser work.
- Do not interpret cells as formulas, markup, code, or paths.
- Sanitize rejection downloads against spreadsheet injection.
- Do not expose internal exception text in row reasons.

## 8. Payment simulation

- The UI visibly states that payment is simulated and real card data must not be used.
- Only exact documented fake numbers are accepted.
- Expiry and security-code controls are non-editable placeholders.
- Card values are not persisted, included in webhook payloads, audit events, error telemetry, or logs.
- Commerce-to-provider initiation uses an idempotency key.
- Mock webhooks use a static bearer token from environment and compare it safely.
- Unique event IDs and transactional state rules prevent duplicate effects.

The bearer-token scheme is not sufficient for a real payment provider. Production requires provider-specific signature verification, timestamp/replay checks, key rotation, durable reconciliation, transport security, compliance review, and strict payment-data minimization.

## 9. Inventory and concurrency security

Overselling and duplicate financial effects are integrity risks:

- Lock inventory rows in a stable order.
- Reserve an entire cart transactionally or reserve nothing.
- Recheck state and stock under lock for every transition.
- Claim expirations atomically and make worker execution idempotent.
- Deduplicate webhook events before stock changes.
- Prevent admin/import stock changes below active reserved quantity.
- Use optimistic concurrency for admin edits.
- Test races against MySQL, not an in-memory database substitute.

## 10. Secrets and environment

- Commit `.env.example`, never real `.env` values.
- Generate distinct application keys, database passwords, session secrets, and webhook tokens.
- Do not embed secrets in frontend bundles, OpenAPI examples, images, logs, CI output, or repository history.
- Local demo defaults must be clearly non-production and changed outside local use.
- Production requires a managed secret store and rotation procedures.

## 11. Browser and HTTP controls

The frontend server should set a reviewed baseline:

- Content Security Policy compatible with the built assets
- `X-Content-Type-Options: nosniff`
- Referrer policy
- Frame-ancestor/clickjacking protection
- Permissions policy appropriate to unused browser capabilities
- HSTS only in correctly configured TLS environments

CORS should not be broadly enabled because the intended browser deployment is same-origin. Production TLS is mandatory even though local Compose may use HTTP.

## 12. Logging, audit, and personal data

- Ordinary logs include operational context but exclude passwords, session/guest tokens, bearer secrets, card values, and unnecessary customer details.
- Admin audit entries are append-only and redact sensitive fields from before/after data.
- Access to order contact/shipping data is role- and ownership-restricted.
- The application does not claim a retention, deletion, or anonymization policy.

Product, Legal, and Security must define account deletion, order retention, privacy-request handling, anonymization, lawful basis, and applicable regulations before production.

## 13. Dependency and supply-chain controls

- Pin exact application dependencies in Composer/npm lockfiles.
- Pin container image versions; avoid floating tags in delivered builds.
- Run Composer and npm vulnerability audits in CI with an explicit triage policy.
- Scan repository secrets and container images.
- Minimize runtime image packages and run application processes as non-root where practical.
- Review generated OpenAPI clients and build scripts as code.

## 14. Availability and operational gaps

The demo includes basic health checks and logs. Production additionally requires:

- Metrics, traces, dashboards, alerting, and incident response
- Database backups and tested restoration
- High availability and failure-domain planning
- Durable payment reconciliation
- Rate-limit/abuse telemetry
- WAF/network controls as justified
- Capacity, load, stress, and recovery tests
- Safe deployment and rollback procedures

Ephemeral Redis intentionally permits loss of pending mock payments on restart and must never be mistaken for a production payment design.

## 15. Pre-production security gate

Before production, at minimum:

1. Complete a threat model and data-flow review.
2. Resolve every Product/Legal/Security decision recorded in the product requirements.
3. Perform static, dynamic, dependency, container, and secret scanning.
4. Commission penetration testing and remediate validated findings.
5. Verify authentication recovery, email ownership, privacy, retention, and payment-provider controls.
6. Test concurrency, idempotency, backup restoration, and failure recovery.
7. Review production infrastructure, TLS, secrets, network exposure, logs, and alerting.
