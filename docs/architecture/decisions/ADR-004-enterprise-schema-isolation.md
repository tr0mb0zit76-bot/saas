# ADR-004: Enterprise schema isolation (optional)

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

Schema-per-tenant — **optional upgrade path** for Enterprise tier only. MVP and Start/Pro remain on **single database + `tenant_id`**.

## Context

Paying customers on Enterprise may require stronger isolation (compliance, noisy neighbor). Full DB-per-tenant is too costly for ops at early scale.

## Consequences

- Build `TenantExportService` and restore runbook before offering schema isolation.
- stancl/tenancy or custom schema switcher — spike when first Enterprise deal requires it.
