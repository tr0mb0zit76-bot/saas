# Pilot run report — 2026-07-12

**Среда:** cloud CI (automated) · **Tenant:** `pilot-co-*` (synthetic)  
**Результат:** ✅ PASS — `PilotSmokeTest` (16 assertions)

## Checklist

| # | Шаг | Статус | Примечание |
| --- | --- | --- | --- |
| 1 | Traklo Pro landing (`SHOWCASE_MODE=traklo_pro`) | ✅ | `Public/TrakloLanding` |
| 2 | Login page | ✅ | CRM domain `/login` |
| 3 | Platform create tenant + onboarding | ✅ | 7 roles, admin user, welcome mail |
| 4 | CRM access (Start plan) | ✅ | leads, contractors OK; mail blocked |
| 5 | Usage limits (5 users) | ✅ | 6-й user → ValidationException |
| 6 | Billing mark paid + PDF | ✅ | status active, Gotenberg mock PDF |
| 7 | Feature override (mail on Start) | ✅ | platform override → `/mail` OK |

## Home-pc manual follow-up

Browser smoke на `http://platform.saas.local` + реальный Gotenberg + SMTP — см. `pilot-smoke-checklist.md`.

**Команда для повторной проверки:**

```powershell
php artisan test tests/Feature/Saas/PilotSmokeTest.php
```
