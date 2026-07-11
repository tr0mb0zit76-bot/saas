# ADR-008: Neutral product branding

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

Platform defaults use **Forward CRM** (configurable via `APP_NAME`). Per-tenant branding in `tenants.settings.branding`. AI assistant **display names** are neutral (Орбита, Коммерция, …); slugs unchanged for audit compatibility. Traklo mobile remains Enterprise SKU only.

## Context

SaaS product is sold to external freight forwarders, not Avtoalyans-only.

## Consequences

- v5 internal deployments may override `.env` with AA branding.
- Public showcase copy becomes tenant-aware or disabled on SaaS single-host lab.
