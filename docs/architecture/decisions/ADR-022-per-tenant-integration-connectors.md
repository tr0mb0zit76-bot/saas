# ADR-022: Per-tenant integration connectors (1C and peers)

**Status:** Accepted  
**Date:** 2026-11-14  
**PO lock:** 2026-11-14 (Orientum clone now; **platform admin UI** ops MVP — Q2 overridden; single `integrations` SKU)  
**Amends:** ADR-015 (integrations MCP + custom adapters) — adds connector packaging, secrets, jobs/webhooks  
**Extends:** ADR-021 (fine-grained settings / content packs) — fifth layer: **Connector instance**  
**Related:** ADR-006 (feature gating), ADR-010 (fail-closed tenant scope), ADR-009 (billing)  
**Team input:** saas-security · saas-qa · saas-privacy (2026-11-14; billing/ops follow-up same day)

## Context

External systems (1C, EPD, …) are **not identical across tenants**: endpoints, credentials, field maps, and sometimes adapter code paths differ. ADR-015 already said Enterprise custom adapters live in-repo under `tenant.settings.integrations[]` + `feature:integrations`, and AA HTTP stubs stay quarantined. PO follow-up: make the packaging model explicit alongside ADR-021 (plan vs setting vs pack), including Orientum cutover (AA 1C config → Orientum-specific, not a global product default).

## Decision

### 1. Where it sits in the ADR-021 model

| Layer | Integrations meaning |
| --- | --- |
| **Plan capability** | `feature:integrations` (Enterprise) — module available: UI, routes, jobs may run |
| **Tenant feature override** | Platform may turn `integrations` on/off per tenant (never self-elevate via settings) |
| **Tenant setting** | Non-secret connector options (enabled flag, adapter_key, schedule, field-map ids) |
| **Content pack** | N/A for live credentials; optional **mapping pack** templates (fictional/example maps only) copied per tenant — same copy-on-write rules as sales-book packs |
| **Connector instance** (this ADR) | Per-tenant binding: `adapter_key` + encrypted secrets + endpoints + mapping → runs only for that `tenant_id` |

Self-serve default remains **MCP** (`mcp_read` / later write) per ADR-015 — not a substitute for a billed 1C connector.

### 2. Plan feature vs connector implementation

- **Plan / checkbox:** only «integrations module available» (`integrations` in plan catalog).  
  **Not** in plan checkboxes: which 1C, which mapping, «Orientum adapter», credentials, webhook URLs.
- **Connector implementation:** platform **allowlist registry** `adapter_key` → PHP class **in this repository** (code review). Tenant row stores `adapter_key`, not an arbitrary FQCN and not an uploaded package.
- **Optional custom adapter for a deal:** new class in repo + registry entry + wire that tenant’s `integrations[]` to that key. **No core fork, no per-tenant Composer package, no remote `include`.**

### 3. Per-tenant config pattern

Recommended shape (conceptual; storage may be `tenants.settings.integrations[]` or a dedicated encrypted table later):

```text
integrations[]: {
  id, adapter_key, enabled,
  endpoints: { ... },          // non-secret or mixed
  mapping: { ... } | mapping_pack_slug,
  secrets: { ... }             // encrypted at rest (Crypt / encrypted cast)
}
```

- **Secrets:** tenant-scoped only; never shared `.env` for 1C passwords/tokens. `APP_KEY` is platform; credential material is per-tenant. Secrets must not appear in Inertia props or plaintext audit.
- **Jobs:** every sync/queue job carries `tenant_id` and sets `TenantContext` before first query; missing context → abort (ADR-010).
- **Webhooks:** resolve tenant via host (`IdentifyTenant`) and/or tenant-bound signed token; verify signature with **that** tenant’s secret; wrong host / bad sig / no context → **404**; never apply payload to another tenant’s entities.

### 4. Platform ops UX (PO-locked, Q2 overridden 2026-11-14)

**Plain meaning:** нужна ли страница в админке `platform.*`, чтобы привязать 1С тенанту, или достаточно `artisan`.

| Path | Who uses it | When |
| --- | --- | --- |
| **Platform admin UI (MVP)** | Platform admin on `platform.*` → Арендаторы → **Ops** | Bind/view connector, clone AA template, set secrets without SSH |
| **Shared service / optional CLI** | Same `TenantConnectorService` used by UI; artisan optional for scripts | Cutover scripts, lab automation |

**Locked for MVP (override):** **platform admin UI** — not artisan-only. Page: `/tenants/{id}/ops` (Inertia `Platform/Tenants/Ops`). Not tenant self-serve. Same surface as ADR-021 pack install + print verification.

### 5. Billing: single `integrations` vs per-connector add-ons (team + architect)

**Locked for MVP:** one Enterprise plan capability `feature:integrations`. Custom adapter work is a **deal / services** cost, not a plan checkbox matrix.  
**Later (optional):** per-connector add-on SKUs only when a **second paying tenant** needs a costly custom adapter and finance wants a line item — amend ADR-009/this ADR then. **Do not overbuild billing WS-6 now.**

Team (security / qa / privacy): agree on **A** — per-connector SKU does not improve isolation or 152-ФЗ posture; it multiplies entitlement gates and CI matrix. Control remains: feature gate + tenant-scoped secrets + allowlist + fail-closed jobs/webhooks.

### 6. What NOT to do

1. Shared credentials or one global 1C mapping for all tenants.  
2. Putting connector choice / «1C Fresh» / Orientum-specific keys into **plan feature checkboxes**.  
3. Uploading PHP/Composer or tenant-supplied FQCN as the adapter.  
4. Reviving AA legacy routes (`/integrations/1c-fresh/*`, Astral, …) as product — keep `SAAS_LEGACY_AA_INTEGRATIONS=false` (ADR-015).  
5. Letting `settings.integrations[].enabled` run when `feature:integrations` is off (settings never elevate).  
6. Dumping full sync/webhook payloads with PII into shared app/queue logs.  
7. Building per-connector billing SKUs or tenant self-serve connector marketplace.

### 7. Orientum cutover (class A) — PO-locked

- **Clone immediately at cutover:** yes. Orientum receives a **cloned** AA connector config (endpoints + mapping schema + secrets) into **Orientum’s** `tenant_id` settings — not a shared env and not product defaults for class B.
- Mapping inheritance = **schema/rules**, not a dump of AA production PII into another tenant.
- Class B new external: no 1C connector unless Enterprise + platform provisions one; provision must not copy Orientum/AA secrets.
- Cutover ops path for MVP: **platform Ops UI** (+ shared service; see §4).

### 8. Privacy

- Synced contractors/contacts/orders live only in tenant-scoped CRM tables.  
- Connector secrets + sync audit in tenant export/delete; wipe/revoke on churn.  
- Prefer not retaining raw 1C payloads; if debug needed — short TTL, per-tenant storage only.  
- Logs: `tenant_id`, entity ids, status, counts — not ФИО/ИНН/phones in shared logs.

### 9. PO answers (closed)

| # | Question | Decision |
| --- | --- | --- |
| 1 | Orientum 1C clone timing | **Immediately** at cutover |
| 2 | Artisan vs platform page | **Platform admin UI for MVP** (Q2 override); shared service may still be called from scripts |
| 3 | Per-connector billable add-ons | **Single `feature:integrations` for MVP**; optional add-ons only when 2nd paying tenant + costly custom adapter (don’t overbuild WS-6) |

## Consequences

### Positive

- One module SKU (`integrations`); many tenant-specific connectors without forking.  
- Aligns with ADR-021: packs/settings/connectors are data/config, not plan matrix noise.  
- Orientum cutover stays isolated; new externals stay clean.  
- Platform Ops UI lets ops bind 1C without SSH while staying off the tenant CRM.

### Negative / debt

- Each custom deal may need an in-repo adapter class (intentional cost).  
- Encrypted settings / secret UX for platform ops must be built carefully.  
- Full adapter runtime (live OData sync) is still deal work beyond registry + bind.

### Mitigations

- Registry allowlist + code review.  
- Fail-closed jobs/webhooks tests (see team).  
- Quarantine legacy AA stubs forever as product path.  
- Shared `TenantConnectorService` for UI and optional scripts.

## Alternatives considered

1. **One global 1C integration for all tenants** — rejected: configs differ; cross-tenant risk.  
2. **Per-tenant micro-fork / private package** — rejected: ops and security nightmare.  
3. **Everything via MCP only** — rejected as sole Enterprise path; MCP stays self-serve default, connectors for deep sync deals.  
4. **Connector type as plan add-on SKUs (1C, Astral, …) in MVP** — rejected by team + architect for ICP 5–50; revisit when 2nd paying tenant needs costly custom adapter.  
5. **Artisan-only MVP** — superseded by PO Q2 override: platform Ops UI is the MVP surface.

## Out of scope

- Full live OData adapter runtime beyond registry + bind + isolation gates.  
- Turning on legacy AA HTTP routes (`SAAS_LEGACY_AA_INTEGRATIONS`).  
- Tenant self-serve connector marketplace.  
- Billing WS-6 per-connector catalog.

## Implementation sketch (P0 landed with ADR-021)

1. Adapter registry (`config/saas-connectors.php`) + encrypted secrets in `tenants.settings.integrations[]`.  
2. **Platform Ops UI** + `TenantConnectorService` (bind / clone AA template into Orientum).  
3. Job middleware: `SetTenantFromJob` + `RequireJobTenantContext`; feature gate in job.  
4. Feature/Saas: `TenantConnectorIsolationTest`.  
5. Legacy AA HTTP stubs stay quarantined (`SAAS_LEGACY_AA_INTEGRATIONS=false`).
