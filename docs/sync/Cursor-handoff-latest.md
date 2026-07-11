# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-11 · **Фаза:** M7 · **Ветка:** `main` (+ `cursor/super-admin-skeleton-4010`)

---

## Продукт: Traklo Pro

- **Pilot:** чистый demo-tenant
- **Billing:** счета / УПД (ADR-009)
- **Storage:** S3 prod, `tenant_local` lab — [[traklo-pro-storage]]
- **Mobile:** один APK + subdomain

---

## Сделано

### M6–M7 (merged в `main`, PR #4)
- Tier A/B `tenant_id`, fail-closed scope, feature gating, TenantStorage
- Composite unique `(tenant_id, email)` и `(tenant_id, role name)`
- 14+ SaaS tests

### Super-admin skeleton (ветка `cursor/super-admin-skeleton-4010`)
- `/platform/tenants` — список, создание, смена plan/status
- `SAAS_PLATFORM_ADMIN_EMAILS` (default: `admin@saas.local`)
- Vue: `Platform/Tenants/Index.vue`
- Меню: «Арендаторы SaaS» в Настройках (только platform admin)
- CrmLayout: скрытие пунктов меню по `tenant.features`
- `BelongsToTenant` на Tier A child models (tasks, leads, contractors…)

---

## На home-pc

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
git pull origin cursor/super-admin-skeleton-4010   # после merge PR
composer install --no-interaction
php artisan migrate --force
npm run build
```

`.env`:

```env
TENANT_STORAGE_DISK=tenant_local
TENANT_STORAGE_FOR_DOCUMENTS=true
SAAS_DEFAULT_TENANT_SLUG=demo
SAAS_PLATFORM_ADMIN_EMAILS=admin@saas.local
```

Login: `admin@saas.local` / `password` → **Настройки → Арендаторы SaaS**

---

## Следующие шаги

1. Merge PR super-admin → main
2. Seed ролей при создании tenant (TenantProvisioner)
3. Billing skeleton (invoice period, ADR-009)

---

## От вас ничего не требуется

Всё на GitHub; `git pull origin main` на home-pc.
