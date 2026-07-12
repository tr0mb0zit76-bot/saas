# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-12 · **Фаза:** Phase 4 · **Ветка:** `cursor/phase4-security-ops-4010`

---

## Env (lab / home-pc)

Применить одной командой:

```powershell
pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
```

```bash
./scripts/apply-saas-lab-env.sh saas.local
```

Ключевые значения:

```env
SHOWCASE_MODE=traklo_pro
SAAS_DEMO_SIGNUP_ENABLED=true
SAAS_DEFAULT_TENANT_SLUG=demo
PLATFORM_DOMAIN=platform.saas.local
SESSION_SECURE_COOKIE=false
TENANT_STORAGE_DISK=tenant_local
```

---

## M9 (done)

- Demo signup `/demo/signup` — **только trial demo**, не paid signup
- CRM onboarding wizard `/onboarding`
- `saas:record-usage` + `tenant_usage_logs` + storage limit
- Suspended → read-only + banner
- Billing: **безнал**, ЮKassa не используется

## M10 (done)

- `TenantExportService` + `php artisan saas:export-tenant {slug}` — manifest ZIP (152-ФЗ prep)
- `SaasAuditGateTest` — P0.10 order visibility gate
- `tenant_audit_logs` + `/audit` в platform admin (tenant create/update/paid/features + demo signup)
- Lab env scripts: `apply-saas-lab-env.sh` / `.ps1` (`SAAS_DEMO_SIGNUP_ENABLED=true`, `SHOWCASE_MODE=traklo_pro`)

## M11 (done)

- Runtime plan editing: лимиты `users`, `orders_per_month`, `storage_mb` на `/plans/{key}/features`
- Audit `plan.updated` в `tenant_audit_logs`

## M12 (done)

- CRM audit hooks: `order.status_changed`, `role.created/updated/deleted`, `user.created`, `payment.recorded`
- `TenantCrmAuditTest` (3 tests)

## Phase 4 (done — core)

- Audit: `payment.reversed`, `user.roles_updated`, `document.signed`, `user.invited`
- CI: `.github/workflows/ci.yml`
- Security headers + optional CSP (`SECURITY_HEADERS_CSP_ENABLED`)
- API rate limit `throttle:api`
- `saas:backup-database` + runbooks (`runbook-*`, `browser-smoke-howto.md`)

## Pending

- M9.5 browser smoke on home-pc — см. **`docs/sync/browser-smoke-howto.md`**
- Phase 4 backlog: 2FA, staging host, queue migrations, monitoring

---

## На home-pc после pull

```powershell
git pull origin main
pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
composer install --no-interaction
php artisan migrate --force
npm run build
```

**Demo flow:** `/` → Демо-доступ → email → login → onboarding → CRM  
**Checklist:** `docs/sync/pilot-smoke-checklist.md`

---

## Следующие шаги

1. **Browser smoke** — `docs/sync/browser-smoke-howto.md` (home-pc, ~30 мин)
2. 2FA tenant-admin, staging, monitoring (Phase 4 remainder)
