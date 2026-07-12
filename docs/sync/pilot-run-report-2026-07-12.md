# Pilot run report — 2026-07-12 (updated)

**Среда:** cloud CI (automated) · **Tenants:** `demo-*`, `pilot-co-*` (synthetic)  
**Результат:** ✅ PASS — `PilotSmokeTest` (28 assertions)

## Checklist

| # | Шаг | Статус | Примечание |
| --- | --- | --- | --- |
| 1 | Traklo Pro landing (`SHOWCASE_MODE=traklo_pro`) | ✅ | `Public/TrakloLanding` |
| 1b | Demo signup `/demo/signup` | ✅ | trial tenant, welcome mail, audit `tenant.demo_signup` |
| 2 | Login page | ✅ | CRM domain `/login` |
| 3 | Platform create tenant + onboarding | ✅ | 7 roles, admin user, audit `tenant.created` |
| 4 | CRM access (Start plan) | ✅ | leads, contractors OK; mail blocked |
| 5 | Usage limits (5 users) | ✅ | 6-й user → ValidationException |
| 6 | Billing mark paid + PDF | ✅ | status active, Gotenberg mock PDF |
| 6b | Platform audit `/audit` | ✅ | created + demo_signup visible |
| 7 | Feature override (mail on Start) | ✅ | platform override → `/mail` OK |

## Home-pc manual follow-up (M9.5)

Browser smoke на `http://saas.local` + `http://platform.saas.local` + реальный Gotenberg + SMTP — см. `pilot-smoke-checklist.md`.

**Подготовка:**

```powershell
git pull origin main
pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
php artisan migrate --force
npm run build
```

**Команда для повторной проверки (CI):**

```powershell
php artisan test tests/Feature/Saas/PilotSmokeTest.php
```
