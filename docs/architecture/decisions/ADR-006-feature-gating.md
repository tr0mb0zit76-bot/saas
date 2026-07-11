# ADR-006: Feature gating (plans)

**Status:** Accepted  
**Date:** 2026-07-11

## Decision

Plan catalog in `config/saas-plans.php` + optional overrides in `tenants.settings.features`. Enforcement via `Tenant::featureEnabled()` and `EnsureFeatureEnabled` middleware (`feature:{key}`). **No Laravel Pennant in MVP.**

## Context

Vendor needs Start/Pro/Enterprise module packaging without per-user flag complexity at launch.

## Consequences

- UI hides menu via Inertia `tenant.features`.
- Route groups gain `feature:*` middleware incrementally.
