# Cursor handoff — SaaS CRM

**Обновлено:** 2026-07-11 · **Фаза:** M6 in progress · **Ветка:** `cursor/saas-security-foundation-4010`

---

## Продукт: Traklo Pro

- **Pilot:** чистый demo-tenant
- **Billing:** счета / УПД (ADR-009)
- **Storage:** S3 prod, `tenant_local` lab — [[traklo-pro-storage]]
- **Mobile:** один APK + subdomain

---

## Сделано (автономная сессия)

### Security & plans (PR #4)
- Fail-closed TenantScope, API/auth tenant middleware, login scoped by tenant_id
- `config/saas-plans.php`, `EnsureFeatureEnabled`, Traklo Pro branding
- AI assistants: Старший, Продавец, РОП, Юрист, СБ, Финансист, Почта
- ADR-004 … ADR-012

### Tier A tenancy
- Migration `2026_07_11_120000_add_tenant_id_tier_a.php` (~35 tables + AI)
- `BelongsToTenant` on: Task, OrderDocument, PaymentSchedule, Mail*, Conversation, ChatMessage, ActivityEvent, GridView, Role, Department, PrintFormTemplate
- `BelongsToTenant` / `TenantScope` safe during migrations (schema column check)

### Storage
- `TenantStorage` helper, `DocumentStorageService` → tenant paths when context set
- `SetTenantFromJob` middleware

### Tests
- `tests/Feature/Saas/*` — 12 tests passing

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

---

## Следующие PR

1. M6 P0.10 audit IDOR scope fixes
2. Tier B migration (sales_scripts, fleet, management…)
3. Route `feature:*` groups
4. Super-admin skeleton (tenants, suspend, invoice period)

---

## От вас ничего не требуется сейчас

Когда будете — merge PR #4 в main и pull на home-pc.
