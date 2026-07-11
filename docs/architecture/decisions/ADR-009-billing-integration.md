# ADR-009: Billing provider integration

**Status:** Proposed  
**Date:** 2026-07-11

## Decision

Use **ЮKassa or CloudPayments** webhooks → `tenant_subscriptions` table. Suspend tenant on payment failure; no custom billing engine.

## Context

Monetization requires recurring plans mapped to ADR-006 feature sets.

## Consequences

- Tables: `tenant_subscriptions`, optional `tenant_usage_logs`.
- Trial job on existing `tenants.trial_ends_at`.
