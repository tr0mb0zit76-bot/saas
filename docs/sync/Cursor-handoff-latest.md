# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-11 · **Фаза:** M7 (pilots ready) · **Ветка:** `main`

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
- Platform admin: `/platform/tenants`

### TenantProvisioner
- При создании tenant: storage + 7 default roles (admin, manager, …)
- `TenantSubscription` sync (trial 14 дней по умолчанию)

### Billing skeleton (ADR-009)
- Таблицы: `tenant_subscriptions`, `tenant_invoices`
- `TenantBillingService::markInvoicePaid()` — продление периода + запись invoice
- `saas:expire-trials` — daily cron, suspend просроченных trial
- Platform UI: колонка «Оплата до», кнопка **Оплачено**
- Trial tenants: доступны через subdomain (`IdentifyTenant` allows `trial`)

### Tests — **17 passed** in `tests/Feature/Saas`

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
Platform: **Настройки → Арендаторы SaaS**

---

## Следующие шаги

1. Tenant onboarding wizard (admin user + invite email при create)
2. PDF/УПД export для `tenant_invoices`
3. Usage limits enforcement (users, orders/month)
4. Pilot с первым внешним экспедитором

---

## От вас ничего не требуется

`git pull origin main` + `migrate` на home-pc.
