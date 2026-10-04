# Product Requirements

## 1. Purpose

The demo is a small but realistic e-commerce application that lets administrators manage a catalog and lets customers or guests search for and purchase physical products. It deliberately demonstrates correctness at integration boundaries: partial CSV import, concurrent stock reservations, asynchronous payment callbacks, and auditable administrative actions.

The requirements distinguish agreed demo behavior from decisions that require Product, Legal, Security, or operational input before production.

## 2. Actors

### 2.1 Anonymous visitor

- Browse, search, filter, sort, and paginate active products.
- View an active product's detail page.
- Maintain a cart in browser local storage.
- Check out as a guest with full contact and shipping details.
- View an order through a private signed link until it expires.
- Create a customer account after a successful guest purchase.

### 2.2 Customer

- Register directly or create an account after guest purchase.
- Log in and out.
- Maintain a server-side cart.
- Merge a browser guest cart into the server cart on login.
- Check out and view authenticated order history/details.
- Use one stored default shipping address.

Customer profile, email, and address editing are out of scope for the demo. Direct registration captures the default address; post-purchase registration copies the completed order's shipping address. The schema supports multiple addresses, but the UI and application rules expose only one default address.

### 2.3 Administrator

- Complete first-run setup when and only when no administrator exists.
- Manage products, categories, taxes, and shipping methods.
- Upload one product image per product.
- Import products from CSV and download rejected rows.
- Adjust inventory with an auditable predefined reason.
- Change allowlisted application settings, including the reservation timeout override.
- List and inspect all orders.
- List manual-review orders and mark them resolved externally with a mandatory note.
- Search the append-only administrative audit log.

The demo supports exactly one administrator. It has no additional-admin creation, deletion, deactivation, demotion, or password-recovery flow. The admin cannot shop, hold a customer cart, or place orders. This separation must be explicit in the README and UI.

## 3. Authentication and account rules

1. First-run admin setup is visible only while no admin exists.
2. Submission rechecks the invariant on the server and creates the sole administrator transactionally.
3. Setup becomes inaccessible after creation; concurrency cannot create two admins.
4. Customer accounts are optional. Guest checkout must never be conditional on account creation.
5. Passwords require at least ten characters, one uppercase character, one number, and one symbol.
6. Email addresses must have valid syntax and be unique case-insensitively.
7. The schema anticipates `email_verified_at`, but email ownership verification is not implemented.
8. If a guest enters an email already owned by a customer account, checkout requires login to prove ownership. The guest may instead use a different email.
9. A post-purchase account offer is displayed after a successful guest purchase. It is not preselected and asks the customer to set a password.
10. Successful post-purchase registration atomically associates that order with the account, copies the shipping address as the default address, and revokes the guest order link.

Optional accounts, guest checkout, and post-purchase registration are demo assumptions that require Product Owner confirmation.

## 4. Catalog

### 4.1 Product

Required fields:

- Name
- SKU
- Price
- Stock on hand, managed through inventory
- Weight in kilograms
- Currency reference

Optional fields:

- Description
- Category reference
- Tax reference
- One locally uploaded picture

Rules:

- Name and SKU are trimmed and cannot be blank.
- SKU uniqueness is case-insensitive and applies permanently, including soft-deleted products.
- Price, stock, and weight cannot be negative. Stock is an integer.
- A product without a category appears under the virtual **Uncategorized** grouping; no special category record is created.
- A product without a tax reference uses a zero tax rate.
- A product without a picture uses a built-in placeholder.
- Product images accept JPEG, PNG, or WebP up to 5 MB, validated by content. Storage uses collision-resistant filenames in a persistent local Docker volume.
- The previous image is removed only after a replacement succeeds. Unreferenced files must be cleaned safely.

Product deletion is conditional:

- A product never referenced by a submitted order or inventory reservation is hard-deleted. Active carts lose the item; product-scoped adjustment history may be deleted with the mistaken record.
- Any order or reservation history forces soft deletion.
- Soft-deleted products disappear from catalog/search/carts/new purchases, while historical snapshots remain readable.
- Soft-deleted SKUs remain reserved. SKU reuse requires future business and audit analysis.

The one-picture design is a demo simplification. Production should use a one-to-many product-image model with ordering, variants, metadata, lifecycle policies, and object storage.

### 4.2 Category

- Admins have full CRUD.
- Names are trimmed and case-insensitively unique among active categories.
- Soft-deleted names may be reused.
- Deletion is soft and clears category references from linked products, making them Uncategorized.

### 4.3 Tax

- Admins have full CRUD.
- Fields include name and rate.
- Names are trimmed and case-insensitively unique among active records; deleted names may be reused.
- Rates range from 0% through 100% inclusive and support fractional percentages.
- Deletion is soft but blocked while active products or shipping methods reference the tax. The UI must warn the administrator and display reference counts.

### 4.4 Shipping method

- Admins have full CRUD.
- Fields include name, fixed amount, currency, and optional tax.
- Names follow active-only, case-insensitive uniqueness and may be reused after soft deletion.
- Amounts cannot be negative.
- Deletion is soft. It clears the selection from active carts, requiring the customer to choose another method. Submitted orders are unaffected because they contain snapshots.

The fixed-amount model is demo-specific. Production shipping may depend on destination, distance, carrier, service level, weight, dimensions, and fulfillment location.

### 4.5 Currency

- Currency records include ISO code, name, symbol, rate relative to the base currency, base indicator, minor units, last rate update time, and soft-delete metadata.
- USD is the sole seeded and active base currency, with rate `1` and two minor units.
- Currency CRUD is not exposed in the demo.
- All monetary records reference a currency. The application rejects mixed-currency carts and orders.
- Orders and payments snapshot the ISO currency code.

The demo is intentionally single-currency. Conversion, rate sourcing, rate history, mixed-currency carts, and cross-currency rounding remain out of scope.

## 5. Inventory

- Inventory is isolated in a one-to-one record per product with `stock_on_hand`.
- Active reservations are separate records.
- `available_stock = stock_on_hand - active_reserved_quantity`.
- Admins cannot reduce stock on hand below active reserved quantity.
- Every admin change creates an immutable adjustment in the same transaction.
- Adjustment data includes previous quantity, new quantity, signed delta, reason code, optional note, actor, and timestamp.
- Allowed manual reasons are `stock_received`, `correction`, `damaged_or_lost`, `customer_return`, and `other`. `other` requires notes.
- CSV-applied changes use `csv_import` and reference the import.

Keeping inventory separate eases a future transition to multiple warehouses or stock locations. Multi-location allocation and transfers are out of scope.

## 6. CSV product import

### 6.1 File contract

- UTF-8, comma-delimited CSV with a header row and standard quoting.
- Headers are trimmed and compared case-insensitively.
- Supported product columns are `name`, `sku`, `description`, `category`, `price`, `stock`, and `weight_kg`.
- `reason` is also accepted and ignored so a downloaded rejection file can be corrected and re-uploaded.
- Any other column rejects the whole file before row processing.
- Images are not supported. Imported products use the placeholder until an admin uploads a picture.
- Defaults are 5 MB and 10,000 data rows; both limits are environment-configurable. Exceeding either rejects the whole file.

Bulk image ingestion is a future Product and architecture decision. Options include controlled remote ingestion and archive-based uploads.

### 6.2 Import choices

The admin must choose one import mode:

- **Create only** (safe default): unknown SKUs are created; existing SKUs are rejected.
- **Update only**: active existing SKUs are updated; unknown SKUs are rejected.
- **Upsert**: unknown SKUs are created and active existing SKUs are updated.

Rows matching soft-deleted SKUs are always rejected.

The admin must also choose an unknown-category policy:

- Create the missing category.
- Import the product as Uncategorized and record a warning.
- Reject the row.

### 6.3 Update semantics

- Blank optional fields clear existing values.
- Blank required fields reject the row.
- New products always use the CSV stock value.
- Existing products ignore CSV stock by default.
- An unchecked **Override stock for existing products** option may be enabled only after the admin confirms that existing stock will change.
- Applied stock overrides create `csv_import` adjustments.
- A stock override below active reserved quantity rejects the row.
- Every occurrence of a SKU duplicated within one upload is rejected.

### 6.4 Result

- The demo processes imports synchronously and commits valid rows independently.
- The response displays total, imported, warning, and rejected counts.
- Rejected original rows are downloadable with a `reason` column containing all validation failures for the row.
- The report must neutralize spreadsheet-formula injection without exposing internal exception details.
- The import record retains actor, original filename, selected policies, counts, and timestamps.

Synchronous processing is a demo decision. Production should use bounded asynchronous jobs, durable result storage, progress reporting, and operational retry controls.

The challenge sample is stored at [examples/code-challenge-products.csv](examples/code-challenge-products.csv). It was downloaded on October 1, 2026, is not imported automatically, and contains intentionally invalid values.

## 7. Search and browsing

- Search active products by case-insensitive partial name, SKU, description, and category name.
- Filter by category, price range, and in-stock status.
- Sort by name or price.
- Use page-number pagination with page sizes 10, 20, or 50; default 20; API maximum 100.
- Provide a dedicated product-detail page.
- Show base prices labeled **excluding tax** on listings and product details.
- Use MySQL queries and appropriate indexes for the demo.

Production should evaluate Elasticsearch for relevance, typo tolerance, synonyms, facets, and indexing throughput, and Redis caching with an explicit invalidation strategy.

## 8. Cart

### 8.1 Persistence

- Guests store carts in browser local storage.
- Authenticated customers store carts server-side.
- Login merges the guest cart into the server cart.
- Quantities for the same product are added and capped at current available stock; the customer is notified of adjustments.
- The browser cart is cleared only after the server confirms a successful merge.
- The server revalidates every client-supplied product, quantity, price, currency, and availability value.

### 8.2 Changes before checkout

- Carts do not reserve inventory.
- Current catalog prices, tax assignments, and shipping configuration are authoritative.
- If price, tax, or shipping values changed, the customer may proceed only after a prominent itemized notification.
- If stock is insufficient, the server does not create an order or reservation. The customer must reduce or remove affected items.
- Reservation is all-or-nothing across the cart.

## 9. Address and checkout

Required contact/shipping fields are recipient name, address line 1, optional address line 2, city, state/region, postal code, ISO country code, phone, and email. Billing address is out of scope because payment is simulated.

Registered customers receive their default address as checkout prefill. Editing checkout data changes the order snapshot, not the saved profile. Profile/address editing is not provided in the demo.

## 10. Tax, totals, and rounding

- Stored product and shipping prices exclude tax.
- Product subtotal = unit price x quantity.
- Tax is calculated and rounded independently per order line.
- Shipping is a separate taxable line.
- Missing tax references imply a zero rate.
- Monetary arithmetic uses fixed-precision decimal values, never binary floating point.
- Values use `DECIMAL(19,4)` storage; currency rates use greater precision.
- Charged/displayed totals use the currency's minor-unit precision and half-up rounding.
- Orders snapshot unit prices, tax names/rates/amounts, shipping method/amount/tax, currency code, and totals.

Tax-exclusive pricing, per-line rounding, and half-up rounding are demo decisions. Production needs configurable inclusive/exclusive pricing and jurisdiction-appropriate line or aggregate rounding.

## 11. Reservations

- Checkout atomically creates an order and reservations for the entire cart.
- The active timeout comes from an allowlisted database setting or falls back to an environment value.
- The demo default is 120 seconds.
- Each reservation stores a fixed expiration timestamp; later setting changes affect only new reservations.
- Reserved units are unavailable to other checkouts.
- Successful on-time payment converts the reservation to a stock deduction.
- Decline, provider error, or failed initiation releases it.
- A separate polling worker expires overdue reservations idempotently using database UTC time.
- Polling interval defaults to 5 seconds and is environment-configurable.
- Concurrent workers must claim rows atomically.

## 12. Simulated payment

### 12.1 User experience

- The form resembles a card form but only the documented test card number is editable and required.
- Expiry and security-code fields are non-editable placeholders.
- A prominent disclaimer says payment is simulated and real card data must not be entered.
- Exact test numbers are:
  - `4000000000010001`: success after a random 2-8 second delay.
  - `4000000000000002`: decline after a random 2-8 second delay.
  - `4000000000090003`: provider error after a random 2-8 second delay.
  - `4000000000080004`: late success after a fixed delay, default 150 seconds.
- Normal range and late delay are configurable in the payment service.
- Card numbers are neither stored nor logged by either service.

### 12.2 Processing

- Commerce sends one payment request with an idempotency key.
- Payment API responds immediately with `202 Accepted` and a provider payment ID.
- The provider enqueues a delayed callback in ephemeral Redis.
- A provider worker invokes the commerce webhook with a static bearer token.
- Failed webhook delivery is retried three times with exponential backoff.
- The commerce webhook is idempotent and handles duplicate and late events.
- If initiation times out, commerce retries once using the same idempotency key. If both attempts fail, order and payment fail and reservations release.
- If a callback later succeeds after failure or expiration, commerce attempts a new atomic stock deduction.
- Sufficient stock moves the order to paid. Insufficient stock moves it to manual review.

Static bearer authentication, ephemeral provider state, and single-container API/worker supervision are mock-only simplifications. Production payment integrations require provider-specific signatures, replay protection, durable state, reconciliation, and compliance controls.

### 12.3 Payment retry and cancellation

- One payment attempt is allowed per demo order.
- Customers cannot retry a failed payment or change payment method on that order.
- Customers cannot cancel while payment is processing.

Production should allow new attempts and payment methods and should support cancellation/void/refund according to provider capabilities and race-safe workflow rules.

## 13. Orders

- Order and payment state are separate concerns with separate histories.
- Orders have ULID primary IDs and unique friendly references such as `ORD-100001`.
- Prefix and initial numeric value are environment-configurable; the start value applies only at initial database setup.
- Customers view their own order history and details.
- Admins can paginate/filter all orders by order state, payment state, date range, customer email, and order number.
- Order detail displays monetary/address snapshots, reservations, payment attempts, and review history.
- Order editing and fulfillment workflows are out of scope.

Guest orders receive an unguessable private access token. Only a hash is stored. Links expire after an environment-configurable period, default 30 days. Production should email order details and the link; email delivery is out of scope.

### 13.1 Manual review

- A late successful payment with insufficient stock places the order in `manual_review`.
- Admins see these orders in a dedicated queue.
- **Mark processed** requires a resolution note and moves the order to `review_processed`.
- This means only “resolved externally”; it does not claim refund, cancellation, fulfillment, or stock allocation.
- Reason, note, actor, and timestamps remain auditable.

Product must define the actual production review, refund, stock, communication, and fulfillment process.

## 14. Payment-status polling

- After submission, the browser polls the order-status endpoint every 2 seconds.
- After 30 seconds it slows to every 5 seconds.
- It stops after a configurable 5 minutes and instructs the customer to revisit order status.
- Stopping browser polling does not cancel or change the order.

Polling is a demo decision. Production should evaluate push delivery such as WebSockets or server-sent events.

## 15. Admin audit log

The append-only audit log covers:

- First admin creation
- Product/category/tax/shipping changes
- Stock adjustments and CSV imports
- Application setting changes
- Manual-review resolution

Records contain actor, action, target, timestamp, and redacted before/after data where appropriate. Passwords, tokens, payment card input, and unnecessary personal data must never be captured. Admins can search the log but cannot alter it.

## 16. Accessibility and responsive design

- Support current desktop and mobile layouts.
- Target WCAG 2.2 AA fundamentals.
- Require keyboard navigation, visible focus, semantic controls, labels, sufficient contrast, accessible errors, and status indicators that do not rely on color alone.
- Formal accessibility certification remains out of scope.

## 17. Seed data

Reference seed data is:

- USD, base rate 1.
- Standard tax, 10%.
- Ground shipping, USD 5.00, Standard tax.
- Air shipping, USD 15.00, Standard tax.
- No users and no products.

## 18. Explicitly out of scope

- Multiple admins and admin recovery
- Email sending, email ownership verification, and password reset
- Customer profile/address editing and multiple-address UI
- Product variants and multiple product images
- Multiple warehouses and inventory transfers
- Real payment processing, refunds, chargebacks, and payment cancellation
- Order fulfillment, shipment tracking, returns, and customer-service workflows
- Currency administration/conversion and multi-currency carts
- Advanced tax jurisdictions and inclusive pricing
- Distance/weight/dimension-based shipping
- Asynchronous CSV import and bulk image ingestion
- Customer account deletion, anonymization, retention automation, and privacy-request workflows
- Full observability, metrics, tracing, alerting, and dashboards
- Production deployment infrastructure

## 19. Decisions requiring production input

Product, Legal, Security, and Operations must resolve at least:

- Guest checkout, account incentives, and order-claim rules
- Email delivery and verification
- Payment retry, cancellation, refund, and reconciliation
- Manual-review resolution and fulfillment outcomes
- Tax inclusion and jurisdictional rounding
- Shipping rate calculation
- SKU reuse and product retention
- Currency conversion/rate ownership
- Profile/address lifecycle and account recovery
- Privacy, retention, deletion, and regulatory obligations
- Performance objectives, capacity, resilience, and disaster recovery

No numeric scale, latency, or throughput claim is made until traffic forecasts and load/stress tests provide evidence.
