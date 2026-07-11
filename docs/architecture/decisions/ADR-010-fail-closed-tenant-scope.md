# ADR-010: Fail-closed tenant scope

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

`TenantScope` applies `whereRaw('0 = 1')` when `TenantContext::id()` is null and bypass is off. Explicit `TenantContext::runWithoutScope()` for seeders, exports, super-admin.

## Context

Fail-open scope allowed cross-tenant reads if middleware misconfigured.

## Consequences

- Console commands must set context or bypass.
- Tests use `TenantContext::set()` or `bypass(true)`.
