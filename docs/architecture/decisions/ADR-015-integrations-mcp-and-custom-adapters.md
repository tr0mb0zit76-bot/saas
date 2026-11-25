# ADR-015: Integrations — MCP-first + Enterprise custom adapters

**Status:** Accepted  
**Date:** 2026-10-15  
**Amended by:** [ADR-022](ADR-022-per-tenant-integration-connectors.md) (2026-11-14) — per-tenant connector instances, secrets, jobs/webhooks, Orientum clone; still MCP-first + in-repo adapters

## Context

AA 1C Fresh / Astral EPD sync is company-specific. Shipping it as a Traklo Pro product feature would lock SaaS to Автоальянс workflows. Pilots need a clear integration model without porting AA stubs 1:1.

## Decision

1. **Self-serve default:** MCP tools + documentation (`feature:mcp_read` / later write).
2. **Enterprise:** custom adapters **in this repository**, scoped per deal under `tenant.settings.integrations[]` and `feature:integrations`. Packaging, secrets isolation, and fail-closed sync/webhooks: **ADR-022**.
3. **AA HTTP stubs** (`/integrations/astral/*`, `/integrations/1c-fresh/*`) are **quarantined**: `SAAS_LEGACY_AA_INTEGRATIONS=false` by default → 404. Do not revive as product; build a new adapter when a deal requires 1C/EPD.
4. UI labels like «Внесено в 1С» stay hidden until `feature:integrations` (Enterprise).

## Consequences

- No AA 1C in Start/Pro.
- Patterns remain in git history / v5.local for reference only.
- Staging/prod must keep `SAAS_LEGACY_AA_INTEGRATIONS=false` unless emergency lab debugging.
