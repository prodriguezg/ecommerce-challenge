# ADR-003: Same-origin SPA with Sanctum cookie authentication

- Status: Accepted
- Date: 2026-10-04

## Context

The only first-party client is a browser SPA. It needs admin/customer sessions, CSRF protection, logout/revocation behavior, and a reviewer-friendly local deployment.

## Decision

Use Laravel Sanctum with secure HTTP-only cookies and CSRF protection. Serve the React app and proxy API routes under one browser origin. Keep admin and customer capabilities strictly separate.

## Consequences

- Browser JavaScript cannot directly read session credentials.
- Same-origin routing avoids unnecessary CORS configuration.
- CSRF setup and cookie attributes must be tested across environments.
- A future native/mobile or third-party API client may require a separate token flow.

## Alternatives considered

- Access/refresh bearer tokens: flexible for multiple client types but introduces browser token storage and rotation complexity not needed here.
- Server-rendered Laravel UI: fewer frontend boundaries but conflicts with the selected independent React container.
