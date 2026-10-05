# API Specification

## 1. Contract ownership

The commerce API is design-first. The authoritative implementation artifact will be `openapi/commerce-api.yaml`; this document defines the conventions and required endpoint inventory that the OpenAPI contract must cover.

CI must:

- Lint and validate the OpenAPI document.
- Verify every implemented HTTP endpoint is documented.
- Run request/response contract tests against the running API.
- Generate the React TypeScript client and fail if committed generated output is stale.
- Prevent undocumented response variants from becoming de facto contracts.

Interactive documentation is enabled for the local demo and disabled or access-controlled by configuration in production.

## 2. General conventions

- Base path: `/api/v1`.
- Media type: `application/json`, except image and CSV upload/download endpoints.
- Identifiers: ULID strings.
- Timestamps: RFC 3339/ISO 8601 in UTC.
- Money: decimal strings plus ISO currency code; never JSON floating-point assumptions.
- Pagination: one-based `page`; allowed `per_page` values 10, 20, 50, API maximum 100; default 20.
- Collection responses include items, current page, page size, total items, and total pages.
- Mutations return the updated resource or an explicit no-content response as documented.
- Admin-editable resources expose a version and require the expected version on update/delete.
- Unknown JSON fields should be rejected for mutation requests unless a schema explicitly allows extension data.

## 3. Authentication and CSRF

- Browser authentication uses Laravel Sanctum HTTP-only cookies.
- The SPA obtains CSRF state through the Sanctum endpoint before state-changing authentication requests.
- Customer and admin permissions are mutually exclusive.
- Unauthenticated, unauthorised, and ownership-failure responses are deliberately distinct only where doing so does not disclose sensitive existence information.
- Guest-order endpoints require the private guest token, not merely an order number and email.

## 4. Error format

Errors use `application/problem+json` and a consistent Problem Details shape:

```json
{
  "type": "https://example.test/problems/validation-error",
  "title": "Validation failed",
  "status": 422,
  "detail": "One or more fields are invalid.",
  "instance": "/api/v1/admin/products/01...",
  "code": "validation_error",
  "errors": {
    "price": ["The price must be greater than or equal to zero."]
  }
}
```

The contract must define at least 400, 401, 403, 404, 409, 413, 415, 422, 429, and 500 behavior where applicable. Validation messages may help users correct input but must not expose stack traces, SQL, paths, or secrets.

## 5. Idempotency and concurrency

- Checkout creation requires an `Idempotency-Key` header scoped to the authenticated customer or guest checkout context.
- Repeating the same key and semantically identical request returns the original result.
- Reusing a key with a different payload returns a conflict.
- The commerce-to-payment request carries its own stable idempotency key and is retried once on ambiguous initiation failure.
- Admin updates include the current version. A stale version returns `409 Conflict` with a safe indication that the resource must be refreshed.
- Webhook provider event IDs are uniquely persisted and processed at most once.

## 6. Public/setup endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health/live` | Process liveness |
| GET | `/health/ready` | Required dependency readiness |
| GET | `/setup/status` | Report whether first-admin setup is available |
| POST | `/setup/admin` | Create the sole admin only when none exists |
| GET | `/products` | Search/filter/sort/page active products |
| GET | `/products/{product}` | Read active product details |
| GET | `/products/{product}/image` | Read an uploaded image or the cacheable SVG placeholder |
| GET | `/categories` | List active categories for storefront filters |
| GET | `/shipping-methods` | List active checkout shipping choices |

`GET /products` query parameters cover `q`, category, minimum/maximum price, in-stock status, sort field/direction, page, and page size.

## 7. Authentication endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/auth/login` | Authenticate an admin or customer and establish a session |
| POST | `/auth/logout` | Invalidate the current session |
| GET | `/auth/me` | Read current principal and role |
| POST | `/customers/register` | Create a customer with the required default address |
| POST | `/guest-orders/{order}/register` | Create account, claim order, copy address, revoke guest token |

Password reset, email verification, email change, and profile/address editing endpoints do not exist in the demo.

## 8. Cart endpoints

Guest cart manipulation occurs in the browser. Server endpoints accept guest cart content only for validation/checkout and never trust client price or availability values.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/cart` | Read authenticated customer's server cart |
| PUT | `/cart/items/{product}` | Set authenticated item quantity |
| DELETE | `/cart/items/{product}` | Remove an item |
| PUT | `/cart/shipping-method` | Select/clear active shipping method |
| POST | `/cart/merge` | Merge supplied guest cart after login |
| POST | `/cart/quote` | Revalidate guest or customer cart and calculate current totals |

Cart responses identify adjustments, unavailable items, current stock limits, and price/tax/shipping changes requiring customer attention.

## 9. Checkout and order endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/checkouts` | Atomically create order/reservations and initiate one simulated payment |
| GET | `/orders` | List authenticated customer's orders |
| GET | `/orders/{order}` | Read an owned customer order |
| GET | `/orders/{order}/status` | Lightweight endpoint used for polling |
| GET | `/guest-orders/{order}` | Read order through guest token |
| GET | `/guest-orders/{order}/status` | Poll guest order through guest token |

Checkout request includes contact/shipping snapshot, selected shipping method, cart lines, and exact fake card number. The response distinguishes accepted/awaiting-payment from immediate initiation failure and returns the private guest link only once when applicable.

No customer cancellation or payment-retry endpoint exists.

## 10. Payment webhook endpoint

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/payments/webhooks/mock` | Receive mock-provider outcome |

Requirements:

- Static bearer token authentication from environment.
- Unique provider event ID and provider payment ID.
- Documented outcome enum and safe provider code.
- Fast idempotent response for duplicate events.
- Transactional state transition and event recording.
- No card number in the webhook.

Production provider integrations require provider-specific signature and replay-protection schemes; the static bearer token is deliberately mock-only.

## 11. Admin catalog endpoints

### 11.1 Products and media

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/products` | Search/page active and deleted products as permitted |
| POST | `/admin/products` | Create product and inventory |
| GET | `/admin/products/{product}` | Read admin product detail |
| PUT | `/admin/products/{product}` | Replace editable product data with version check |
| DELETE | `/admin/products/{product}` | Conditional hard/soft delete |
| POST | `/admin/products/{product}/image` | Validate and replace local product image |
| DELETE | `/admin/products/{product}/image` | Remove uploaded image and restore placeholder behavior |

### 11.2 Categories

| Method | Path | Purpose |
| --- | --- | --- |
| GET/POST | `/admin/categories` | List/create categories |
| GET/PUT/DELETE | `/admin/categories/{category}` | Read/update/soft-delete category |

### 11.3 Taxes

| Method | Path | Purpose |
| --- | --- | --- |
| GET/POST | `/admin/taxes` | List/create taxes |
| GET/PUT/DELETE | `/admin/taxes/{tax}` | Read/update/conditionally soft-delete tax |

Tax-delete conflict response includes safe counts of blocking active product and shipping-method references.

### 11.4 Shipping methods

| Method | Path | Purpose |
| --- | --- | --- |
| GET/POST | `/admin/shipping-methods` | List/create methods |
| GET/PUT/DELETE | `/admin/shipping-methods/{shippingMethod}` | Read/update/soft-delete method |

### 11.5 Inventory

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/products/{product}/inventory` | Read on-hand, reserved, and available quantities |
| POST | `/admin/products/{product}/inventory-adjustments` | Set new on-hand quantity with reason/note |
| GET | `/admin/products/{product}/inventory-adjustments` | Page immutable adjustment history |

## 12. Admin import endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/admin/product-imports` | Synchronously validate/process bounded CSV upload |
| GET | `/admin/product-imports/{import}` | Read import metadata/counts |
| GET | `/admin/product-imports/{import}/rejections.csv` | Download sanitized rejected rows |

Multipart request fields include import mode, unknown-category policy, stock-override flag, and explicit stock confirmation when enabled. The OpenAPI schema must enumerate whole-file and row-level failure behavior.

## 13. Admin order/review endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/orders` | Filter/page all orders |
| GET | `/admin/orders/{order}` | Read full admin order timeline/detail |
| GET | `/admin/manual-reviews` | List pending review orders |
| POST | `/admin/manual-reviews/{review}/resolve` | Mark externally resolved with mandatory note/version |

No generic order-edit endpoint exists.

## 14. Admin settings and audit endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/settings` | Read allowlisted settings and active source/value |
| PUT | `/admin/settings/{key}` | Set validated database override |
| DELETE | `/admin/settings/{key}` | Remove override and fall back to environment |
| GET | `/admin/audit-logs` | Filter/page append-only admin audit events |

The generic settings endpoint cannot invent keys. The initial allowlist contains the reservation timeout override.

## 15. Media and CSV response safety

- Product media is served with a controlled content type, nosniff behavior, and no user-provided executable filename.
- Upload endpoints reject unsupported content and oversized bodies before persistence.
- Rejection CSV downloads use a safe filename and neutralize cells that spreadsheet software could interpret as formulas.
- CSV responses preserve a `reason` column that the importer explicitly accepts and ignores on re-upload.

## 16. Mock payment API contract

The payment service has its own small contract, independent of commerce inventory semantics.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health/live` | Process/supervisor liveness |
| GET | `/health/ready` | Redis/worker readiness |
| POST | `/api/v1/payments` | Accept an idempotent simulated payment request |

Payment request includes commerce payment/order references, decimal amount, ISO currency code, callback URL/reference governed by configuration, and the exact test card number. It does not include reservation expiration.

Response is `202 Accepted` with provider payment ID and processing status. Duplicate idempotency keys with the same payload return the original acceptance; conflicting payloads return `409`.

The mock's normal delay range defaults to 2-8 seconds. Late-success delay defaults to 150 seconds. Redis persistence is disabled, so state and pending callbacks may disappear on restart.

## 17. API documentation route

The local demo exposes rendered documentation at `/api/docs` (or an equivalent path documented in the README). A configuration flag must disable or protect it for production-like environments.
