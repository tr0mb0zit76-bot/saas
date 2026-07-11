# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-11 · **Фаза:** M7+ (platform admin) · **Ветка:** `main`

---

## Продукт: Traklo Pro

- **Pilot:** чистый demo-tenant
- **Billing:** счета / УПД вручную (ADR-009) — skeleton готов
- **Storage:** S3 prod, `tenant_local` lab
- **Mobile:** один APK + subdomain

---

## Сделано (в `main`)

### Tenancy & security
- Tier A/B `tenant_id`, fail-closed scope, feature gating, TenantStorage
- Composite unique email/roles per tenant
- Platform admin: `/platform/*` (отдельная супер-админка)

### Platform Super-Admin (`/platform`)
- **Обзор** — `/platform` (статистика арендаторов, trial expiring)
- **Арендаторы** — `/platform/tenants` (create/update/billing)
- **Тарифы и модули** — `/platform/plans` (матрица Start/Pro/Enterprise)
- **Модули арендатора** — `/platform/tenants/{id}/features` (override `settings.features`)
- Каталог модулей: `config/saas-features.php` + `SaasFeatureCatalog`
- Меню CRM: **Настройки → Platform Admin** (для `SAAS_PLATFORM_ADMIN_EMAILS`)

### Dev tooling
- **Ponytail** rules: `.cursor/rules/ponytail.mdc` + SaaS carve-out
- **Vite chunking:** ag-grid, mermaid, tiptap, grapesjs, vue-flow, page-* chunks

### TenantProvisioner + Billing
- При создании tenant: storage + 7 default roles + subscription
- `TenantBillingService::markInvoicePaid()`, `saas:expire-trials` cron

### Tests — **20 passed** in `tests/Feature/Saas`

---

## На home-pc

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
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
SAAS_TRIAL_DAYS=14
```

Login: `admin@saas.local` / `password`  
Platform: **Настройки → Platform Admin** или `/platform`

---

## Следующие шаги

1. Tenant onboarding wizard (admin user + invite email при create)
2. PDF/УПД export для `tenant_invoices`
3. Usage limits enforcement (users, orders/month)
4. Pilot с первым внешним экспедитором
5. (Опционально) runtime-редактирование тарифов в БД вместо config

---

## От вас ничего не требуется

`git pull origin main` + `migrate` + `npm run build` на home-pc.
