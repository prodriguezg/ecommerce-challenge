# ADR-002: Laravel, React, MySQL, and conventional Laravel layers

- Status: Accepted
- Date: 2026-10-04

## Context

The application requires a polished SPA, relational constraints, transactions, row locks, admin workflows, file import, and a small payment simulator. The implementation should be idiomatic and achievable within challenge scope.

## Decision

Use PHP/Laravel for both APIs, React/TypeScript/Vite with Material UI for the frontend, and the current stable/LTS MySQL release with InnoDB for durable commerce data. Structure Laravel with controllers, Form Requests, policies/middleware, services, Eloquent models/query scopes, API Resources, commands, and selective events/jobs. Services use Eloquent directly; no repository abstraction or full DDD layer is imposed.

## Consequences

- Laravel provides mature validation, security, database, and testing conventions.
- React keeps frontend/backend boundaries explicit.
- MySQL supports the required constraints, transactions, and concurrency behavior.
- Business transactions need careful lock ordering and MySQL-backed tests.
- Direct Eloquent coupling is accepted because no alternate durable store is planned.

## Alternatives considered

- NestJS, ASP.NET Core, or Spring Boot: viable enterprise stacks but not selected.
- Vue: natural Laravel pairing, but React was preferred.
- SQLite: simpler setup but unsuitable for the intended multi-container concurrent writes.
- Repository interfaces/full DDD: additional ceremony without a demonstrated need.
