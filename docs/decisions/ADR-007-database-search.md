# ADR-007: MySQL-backed product search for the demo

- Status: Accepted
- Date: 2026-10-04

## Context

The challenge requires product search, but no catalog scale or relevance target is provided. Adding a search cluster would make the local environment heavier without evidence that it is needed.

## Decision

Search active products in MySQL by case-insensitive partial name, SKU, description, and category, with category, price, and stock filters; name/price sorting; and page-number pagination. Add indexes based on actual query plans.

## Consequences

- The demo remains self-contained and easy to run.
- Advanced relevance, typo tolerance, synonyms, and rich facets are limited.
- No unverified capacity claim is made.
- Production should evaluate Elasticsearch and Redis caching after requirements and load tests exist.

## Alternatives considered

- Elasticsearch immediately: more powerful but introduces operational and synchronization complexity not justified by demo evidence.
- Client-side search: cannot scale, secure, or paginate correctly.
