# ADR-009: Billing — счета и УПД (B2B)

**Status:** Accepted  
**Date:** 2026-07-11 (updated)

## Decision

MVP billing: **manual invoice workflow** (счёт → оплата → УПД), not online payment gateway. Track subscription state in `tenant_subscriptions` with status `trial | active | past_due | suspended`. Admin marks invoice paid; system extends period and keeps plan features.

Optional later: ЮKassa/CloudPayments for self-service — separate ADR amendment.

## Context

B2B экспедиторы привыкли к счетам и закрывающим документам. Product director confirmed no payment provider at launch.

## Consequences

- Super-admin UI: create tenant, assign plan Start/Pro/Enterprise, set `billing_period_end`, suspend on non-payment.
- No webhook MVP; cron job `ExpireTrials` on `trial_ends_at`.
- Usage limits (users, orders/month) enforced in app; overage handled commercially offline.
