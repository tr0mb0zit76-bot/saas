# Staging (боевой тестовый сервер) — checklist

> ADR: `docs/architecture/decisions/ADR-014-staging-topology.md`  
> Env template: `.env.staging.example`  
> Deploy: `scripts/deploy-staging.sh`

## Human blockers (before first deploy)

- [ ] Linux VM + SSH
- [ ] DNS: apex + `*.{CRM_DOMAIN}` + `platform.{CRM_DOMAIN}`
- [ ] Wildcard TLS
- [ ] Secrets out-of-band: `APP_KEY`, DB, Redis, S3, SMTP, Gotenberg
- [ ] Accept ADR-014 (single VM, manual migrate)
- [ ] Demo signup policy (`SAAS_DEMO_SIGNUP_ENABLED`)

## Host bootstrap

- [ ] nginx + PHP-FPM 8.3, MySQL 8, Redis, Supervisor
- [ ] Gotenberg container
- [ ] Clone repo; copy `.env.staging.example` → `.env`
- [ ] `php artisan key:generate` if needed
- [ ] cron: `* * * * * php artisan schedule:run`
- [ ] Supervisor: `queue:work redis`
- [ ] Nightly: `php artisan saas:backup-database` (see `runbook-backup-restore.md`)

## App deploy

```bash
bash scripts/deploy-staging.sh main
# first time, after migrate:
php artisan db:seed --class=SaasDemoSeeder --force   # only if demo tenants wanted
php artisan storage:link   # also in deploy-staging.sh
```

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
- [ ] `ForceLabHttpSessionCookies` is no-op outside `local`/`testing` (do not remove middleware; env gate is enough)
- [ ] Do **not** weaken `IdentifyTenant` / `TenantScope` / `platform.admin`
- [ ] Tenants resolve on HTTPS subdomains
- [ ] Platform login on `platform.{CRM_DOMAIN}`
- [ ] Files land on `tenant_s3`
- [ ] Change seed demo password before any public/VPN exposure

## Gate before calling staging “live”

- [ ] CI green on release commit
- [ ] Isolation: demo-a ≠ demo-b (or `tests/Feature/Saas` on CI)
- [ ] M9.5 browser smoke over HTTPS (`browser-smoke-howto.md`)
- [ ] Backup command dry-run once

## Explicitly later (not blockers)

- 2FA for tenant-admin
- Queue-wrapped migrations
- Full APM / error dashboards
- Porting AA-only v5 (1C, EPD, Load Board, intercompany)
