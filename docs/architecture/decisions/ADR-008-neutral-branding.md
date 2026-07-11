# ADR-008: Product branding — Traklo Pro

**Status:** Accepted  
**Date:** 2026-07-11 (updated)

## Decision

Platform product name: **Traklo Pro** (`APP_NAME`). Per-tenant branding in `tenants.settings.branding`. AI assistant display names: **Старший, Продавец, РОП, Юрист, СБ, Финансист, Почта** (slugs unchanged for audit). External-party mobile (бывший Traklo carrier app) — Enterprise SKU `traklo_mobile`.

## Context

SaaS sold to external freight forwarders under Traklo Pro brand. Pilot tenant: clean **demo**, not Avtoalyans production data.

## Consequences

- v5 internal AA deployments override `.env` locally if needed.
- Showcase copy becomes tenant-aware on SaaS hosts.
