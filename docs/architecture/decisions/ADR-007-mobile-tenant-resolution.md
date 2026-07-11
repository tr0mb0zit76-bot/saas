# ADR-007: Mobile tenant resolution

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

1. **Primary:** subdomain `{slug}.crm.ru` (or lab `X-Tenant-Slug`).
2. **Post-auth authoritative:** `user.tenant_id` via `SetTenantFromAuthenticatedUser`.
3. **Mismatch** between header/subdomain and authenticated user → **403**.

## Context

Mobile app and Sanctum API must not leak cross-tenant data.

## Consequences

- API group includes `IdentifyTenant` + `SetTenantFromAuthenticatedUser`.
- Login scoped by `tenant_id` in credentials.
