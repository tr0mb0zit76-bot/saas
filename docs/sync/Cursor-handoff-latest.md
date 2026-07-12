# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-12 · **Фаза:** M9 · **Ветка:** `main`

---

## Platform login 419 (lab HTTP)

- **Fix:** `configureLabHttpSessionCookies()`, `SESSION_SECURE_COOKIE=false`, Chrome/Edge (не Simple Browser).

---

## Продукт: Traklo Pro

- **Pilot / demo:** self-service **только demo-доступ** (trial Start), не paid signup
- **Billing:** безнал, счета/УПД вручную (ADR-009). **ЮKassa не планируется.**
- **Storage:** S3 prod, `tenant_local` lab

---

## Сделано (M9)

### M9.1 Demo signup (demo access only)
- `GET/POST /demo/signup` при `SAAS_DEMO_SIGNUP_ENABLED=true`
- `DemoSignupService`: trial Start, `settings.demo_tenant=true`, welcome mail
- CTA на Traklo landing → `demoSignupUrl`
- Throttle 3/hour

### M9.2 CRM onboarding wizard
- `/onboarding` — own company, ИНН, timezone, опционально первый заказчик
- `EnsureOnboardingComplete` middleware (authenticated only)
- Platform routes исключены

### M9.3 Usage metering
- Таблица `tenant_usage_logs`
- Cron `saas:record-usage` daily 06:15
- Лимит `storage_mb` enforced в `TenantStorage::put`

### M9.4 Suspend read-only
- `IdentifyTenant` пропускает `suspended`
- `EnsureTenantWritable` — блок POST/PATCH/DELETE, GET разрешён
- Banner в `CrmLayout` при `tenant.read_only`

### M8 (ранее)
- Platform onboarding, usage limits, invoice PDF, pilot smoke

### Tests — **40/41 SaaS passed** (1 pre-existing PlatformPortalTest flake)

---

## На home-pc

```powershell
git pull origin main
composer install --no-interaction
php artisan migrate --force
npm run build
```

`.env` для demo landing:

```env
SHOWCASE_MODE=traklo_pro
SAAS_DEMO_SIGNUP_ENABLED=true
SAAS_TRIAL_DAYS=14
```

Demo: витрина → «Демо-доступ» → `/demo/signup` → email с паролем → `/onboarding` → CRM.

---

## Следующие шаги

1. **M9.5** — browser smoke с первым внешним экспедитором (home-pc)
2. P0.10 / P1.1 audit (finance/leads scope) — по `saas-audit-remediation.md`
3. TenantExportService (152-ФЗ)
4. Audit log `tenant_audit_logs`
5. Runtime plan editing в platform (опционально)

---

## От вас

`git pull` + `migrate` + `npm run build`. Для demo signup: `SAAS_DEMO_SIGNUP_ENABLED=true`.
