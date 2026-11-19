# ADR-014: Staging (боевой тестовый) topology & deploy

**Status:** Proposed  
**Date:** 2026-10-15

## Context

Lab (`saas.local` / OSPanel) proves tenancy and product UX. Phase 4 still lacks a production-like host. Combat-test needs HTTPS, real DNS/subdomains, secure cookies, queue workers, backups, and S3-compatible files — without waiting for 2FA or full observability.

No deploy script exists yet; P4 checklist lists “миграции через queue” and monitoring as open items.

## Decision

### Topology (single VM first)

One Linux VM (or VPS) as **staging**, not HA:

| Component | Choice |
| --- | --- |
| Web | nginx + PHP-FPM 8.3 |
| App | Laravel from `main` (or release tag) |
| DB | MySQL 8 on same host or managed MySQL (separate from AA prod / lab) |
| Cache / queues | Redis + `queue:work` under Supervisor |
| Scheduler | cron `* * * * * php artisan schedule:run` |
| Print | Gotenberg container (`DOC_PREVIEW_DRIVER=gotenberg`) |
| Files | `TENANT_STORAGE_DISK=tenant_s3` (Yandex Object Storage / MinIO) — ADR-005 |
| Platform | `platform.{CRM_DOMAIN}` + wildcard `*.{CRM_DOMAIN}` |

### Domain model

- Apex / marketing: `https://{CRM_DOMAIN}/`
- Tenants: `https://{slug}.{CRM_DOMAIN}/`
- Platform: `https://platform.{CRM_DOMAIN}/`
- Wildcard TLS (Let’s Encrypt DNS-01 or provider cert covering `*.domain`)

### Env invariants (vs lab)

| Lab | Staging |
| --- | --- |
| `APP_ENV=local`, `APP_DEBUG` often true | `APP_ENV=production`, `APP_DEBUG=false` |
| `SESSION_SECURE_COOKIE=false` | `SESSION_SECURE_COOKIE=true` |
| `ForceLabHttpSessionCookies` OK | **disabled** / not registered |
| `tenant_local` | `tenant_s3` |
| Demo signup optional | Prefer invite-only (`SAAS_DEMO_SIGNUP_ENABLED=false`) unless stakeholder wants open demo |

### Deploy process (MVP)

1. CI green on `main` (`.github/workflows/ci.yml`).
2. SSH: `git fetch && git checkout <tag|main> && git pull`.
3. `composer install --no-dev --optimize-autoloader`.
4. `npm ci && npm run build`.
5. `php artisan migrate --force` (manual on first hosts; queue-wrapped migrate is a later P4 hardening).
6. `php artisan config:cache && route:cache && view:cache`.
7. Restart php-fpm + queue workers.
8. Smoke: `PilotSmokeTest` against staging DB **or** manual `docs/sync/browser-smoke-howto.md` over HTTPS.

Script target: `scripts/deploy-staging.sh` (Linux) — not Windows OSPanel lab scripts.

### Out of scope for first staging cut

- 2FA for tenant-admin
- Full APM / error-rate dashboards (minimum: HTTP uptime ping + log shipping)
- Queue-driven migrations
- Schema-per-tenant, custom domains, live payment gateway (ADR-009 remains manual invoices)

## Consequences

### Positive

- Clear bar: “combat-test ready” ≠ “enterprise ops complete”
- Aligns with ADR-002/005/010 without inventing a second tenancy model
- Lab scripts stay lab-only; staging gets one Linux path

### Negative

- Single-VM blast radius; acceptable for internal / pilot combat-test
- Manual migrate until queue job lands

## Alternatives considered

1. **Deploy OSPanel lab to public IP** — rejected: Secure cookies, TLS, and wildcard DNS fight lab middleware (`ForceLabHttpSessionCookies`).
2. **Full k8s / multi-AZ** — rejected for combat-test (YAGNI).
3. **Block staging until 2FA + monitoring** — rejected: delays pilot feedback; both are Phase-4 remainder after first host is live.
