# ADR 0002: Initial multi-tenancy strategy

## Status

Proposed.

## Context

The product must serve multiple forwarding companies. The current CRM appears
to be a single-company system, but its schema and code are not available yet.

## Decision

Use a shared application and shared database for MVP, with mandatory `tenant_id`
on tenant-owned records and explicit tenant context in business services.

## Rationale

- Simpler to operate for an MVP.
- Easier to migrate the first tenant from the current CRM.
- Can still enforce strong tenant isolation when implemented consistently.
- Does not block future enterprise isolation.

## Consequences

- Every tenant-owned query must be scoped by tenant.
- Unique constraints must include `tenant_id`.
- Permission checks must include tenant membership.
- Audit logs must capture tenant and actor context.
- Later enterprise isolation may require data movement tooling.
