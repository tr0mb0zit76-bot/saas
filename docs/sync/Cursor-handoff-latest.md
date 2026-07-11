# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-11 · **Фаза:** M6 → M7 · **Ветка:** `cursor/saas-security-foundation-4010`

---

## Продукт: Traklo Pro

- **Pilot:** чистый demo-tenant
- **Billing:** счета / УПД (ADR-009)
- **Storage:** S3 prod, `tenant_local` lab — [[traklo-pro-storage]]
- **Mobile:** один APK + subdomain

---

## Сделано (автономная сессия 2)

### Tier B tenancy
- Migration `2026_07_11_130000_add_tenant_id_tier_b.php` (~75 таблиц: sales_scripts*, fleet_*, business_processes, management_*, budget_*, order graph)
- `BelongsToTenant` на: SalesScript, SalesBookArticle, BusinessProcess, FleetVehicle/Driver/Trip, LoadBoardPost, DispositionEntry, KpiSetting, OrderLeg, Cargo

### Composite unique per tenant
- `users`: unique `(tenant_id, email)` — migration `130001`
- `roles`: unique `(tenant_id, name)` — migration `130002`
- `Role::$fillable` + `tenant_id`; fix `SaasDemoSeeder::seedRoles(Tenant $tenant)`

### Feature gating (M7.1)
- `feature:*` middleware на routes: mail, sales_*, load_board, fleet, documents, payment_schedules, management_accounting, import_cost, proposals_html, mcp_read

### Tests — **15 passing** in SaaS suite
- `TenantTierBIsolationTest`, `TenantEmailUniquenessTest`, `TenantFeatureGatingTest`

---

## На home-pc (когда вернётесь)

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin cursor/saas-security-foundation-4010
composer install --no-interaction
php artisan migrate --force
php artisan config:clear
npm run build
```

`.env` (lab, без S3):

```env
TENANT_STORAGE_DISK=tenant_local
TENANT_STORAGE_FOR_DOCUMENTS=true
```

Login: `admin@saas.local` / `password`

---

## Следующие PR

1. Super-admin skeleton (tenants CRUD, suspend, plan override)
2. BelongsToTenant на оставшиеся Tier A child models
3. M6 P0.10 — audit grep + cherry-pick scope fixes из v5
4. Merge PR #4 → main

---

## От вас ничего не требуется

Когда будете — merge PR в main и `migrate` на home-pc.
