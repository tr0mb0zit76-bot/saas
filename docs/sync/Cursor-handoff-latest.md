# Cursor handoff — SaaS CRM

**Обновлено:** 2026-07-11 · **Фаза:** M6 in progress · **Ветка:** `cursor/saas-security-foundation-4010`

---

## Продуктовая стратегия

Полный бриф: `docs/architecture/saas-product-roadmap-brief.md`  
ADR: `004`–`010` в `docs/architecture/decisions/`

**Позиционирование:** vertical SaaS для экспедиторов (5–50 чел.), не «ещё одна CRM», а цепочка лид → заказ → документы → график оплат + mobile + AI command bar.

**Тарифы:** Start / Pro / Enterprise — каталог `config/saas-plans.php`, enforcement через middleware `feature:*`.

**Брендинг:** нейтральный default **Forward CRM**; per-tenant `settings.branding`. AI-ассистенты переименованы (Орбита, Коммерция, …), slug в audit без изменений.

---

## Сделано в этой итерации

- ADR-004 … ADR-010
- Fail-closed `TenantScope` (ADR-010)
- `SetTenantFromAuthenticatedUser` + API tenant middleware
- Login scoped by `tenant_id`
- `config/saas-plans.php` + `Tenant::featureEnabled()`
- Neutral AI personas + app title defaults
- Tests: `tests/Feature/Saas/TenantSecurityTest.php`

---

## Следующие PR (порядок)

1. Tier A `tenant_id` migration (~40 таблиц)
2. M6 audit P0.10 / P1.1
3. `TenantStorage` + file isolation slice
4. Route `feature:*` groups (mail, finance, …)
5. Billing skeleton (`tenant_subscriptions`)

---

## Локальный OSPanel (Windows)

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
pwsh -File scripts/setup-os-panel.ps1
```

Если **404** на `/`: `pwsh -File scripts/apply-saas-lab-env.ps1`

Открыть: **http://saas.local** · Login: **admin@saas.local** / **password**

---

## Demo tenants

| Slug | User |
|------|------|
| demo | admin@saas.local |
| demo-a | manager@demo-a.saas.local |
| demo-b | manager@demo-b.saas.local |

---

## Open questions

1. Имя продукта: Forward CRM или другое?
2. Mobile: один APK + subdomain vs white-label Enterprise?
3. Billing: ЮKassa vs CloudPayments?
