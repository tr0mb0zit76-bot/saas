# ADR-005: Tenant-aware file storage

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

All tenant-owned files use prefix `storage/tenants/{tenant_id}/` via a `TenantStorage` helper. S3-compatible paths use the same prefix.

## Context

Current v5 upload paths are global; SaaS requires document isolation per customer.

## Consequences

- New helper before migrating all upload services.
- First vertical slice: order documents.
