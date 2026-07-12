# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-12 · **Фаза:** M11 · **Ветка:** `cursor/m11-plan-limits-4010`

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

## Pending

- M9.5 browser smoke on home-pc (Chrome) — automated `PilotSmokeTest` ✅, manual checklist open

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

1. M9.5 browser smoke (home-pc, Chrome) — `docs/sync/pilot-smoke-checklist.md`
2. M12 CRM-side audit events (order status, roles) — phase 4
