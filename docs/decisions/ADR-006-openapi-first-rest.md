# ADR-006: Design-first REST/OpenAPI contract and generated TypeScript client

- Status: Accepted
- Date: 2026-10-04

## Context

The React frontend and Laravel API are separate applications. Reviewers need a clear, testable contract, and undocumented endpoint drift would undermine the value of that boundary.

## Decision

Use versioned JSON REST endpoints under `/api/v1`. Maintain an authoritative design-first OpenAPI YAML document. Generate the frontend TypeScript client/types from it. CI validates the schema, endpoint coverage, responses, and generated-client freshness. Errors use a consistent Problem Details shape.

## Consequences

- API design is reviewable before controller implementation.
- Frontend models are not duplicated manually.
- Contract changes require deliberate updates and regeneration.
- CI needs route/response validation tooling and representative contract tests.
- Local interactive documentation can be generated from the same source.

## Alternatives considered

- GraphQL: flexible querying with unnecessary schema/client complexity here.
- Generated-from-code OpenAPI: convenient but tends to describe implementation after the fact.
- Handwritten frontend request types: fast initially but prone to drift.
