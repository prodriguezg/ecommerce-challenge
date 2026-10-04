# ADR-008: Browser polling for asynchronous payment status

- Status: Accepted
- Date: 2026-10-04

## Context

Payment completion arrives asynchronously through a provider webhook. The browser needs to display progress and terminal outcomes, including a deliberately slow late-success scenario.

## Decision

Poll a lightweight order-status endpoint every two seconds, slow to every five seconds after 30 seconds, and stop after a configurable five minutes with instructions to revisit the order. Stopping polling never changes the order.

## Consequences

- The implementation is simple, observable, and robust to brief browser disconnects.
- Polling creates repeated requests and is not instant at high scale.
- The endpoint must be ownership/token protected and inexpensive.
- Production should evaluate WebSockets or server-sent events based on scale and infrastructure.

## Alternatives considered

- WebSockets: low-latency push with more connection infrastructure.
- Server-sent events: simpler one-way push, still requiring durable event/reconnect design.
- No live status: poor UX for asynchronous processing.
