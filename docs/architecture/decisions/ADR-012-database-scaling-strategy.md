# ADR-012: Database scaling strategy

**Status:** Accepted  
**Date:** 2026-07-11

## Context

Traklo Pro starts with single MySQL + `tenant_id` (ADR-002). Product direction: **S3 for files**, **shared LLM**, growth beyond 200 tenants, AI/agent tables growing fast.

## Decision

### Phase map (not calendar — trigger-based)

| Phase | Trigger | Architecture |
| --- | --- | --- |
| **P0 — Now** | MVP, ≤50 tenants | Single MySQL `saas_crm`, all tables + `tenant_id`, S3 keys via `TenantStorage` |
| **P1** | DB CPU >70% sustained or >200 tenants | Read replica for reports/analytics; connection pooling (ProxySQL / RDS proxy) |
| **P2** | AI log tables >30% DB size | Move append-only AI audit to **same server, separate schema** `saas_ai` OR partition `ai_*` by month |
| **P3** | Enterprise compliance deal | **Schema-per-tenant** for that customer (ADR-004); core app unchanged |
| **P4** | >500 active tenants | Evaluate Citus / Vitess / selective DB-per-tenant for top 10% revenue |

### What stays single for long time

- Application code (Laravel)
- Redis cache / sessions (tenant-prefixed keys)
- S3 bucket(s) with prefix isolation
- LLM API (vendor pool)

### What splits first (order)

1. **Object storage (S3)** — day one, not MySQL blobs
2. **Read replica** — heavy grids, KPI, management accounting
3. **AI append logs** — optional schema split before business data split
4. **Business data schema-per-tenant** — Enterprise only

### Backup / export

- Full DB backup — platform ops
- **Per-tenant export** — `TenantExportService` (SQL slice + S3 prefix list) for 152-ФЗ and churn

### Connection limits

- Horizon/queue workers: pool per worker, not per tenant
- Long reports: replica + `TenantContext` mandatory

## Consequences

- Do not store file binaries in MySQL (already mostly true).
- Index `(tenant_id, created_at)` on high-volume tables early.
- Avoid cross-tenant JOINs in reporting — aggregate up from tenant-scoped queries.

## Relation to ADR-002

ADR-002 limit «~200 tenants» is **operational guidance**, not hard cap. ADR-012 defines **when and how** to evolve without rewrite.
