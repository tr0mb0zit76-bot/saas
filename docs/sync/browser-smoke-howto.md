# Browser smoke — как прогнать на home-pc

**Цель:** ручная проверка ~30 мин после `git pull`. Автоматический аналог: `php artisan test tests/Feature/Saas/PilotSmokeTest.php`.

## 1. Подготовка (один раз за сессию)

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
composer install --no-interaction
php artisan migrate --force
npm run build
```

OSPanel: домены `saas.local` и `platform.saas.local` → папка `public`, PHP 8.3, MySQL `saas_crm`.

## 2. Автопроверка (опционально, 30 сек)

```powershell
php artisan test tests/Feature/Saas/PilotSmokeTest.php
```

Ожидание: `PASS`, 28 assertions.

## 3. Browser — CRM + demo signup

Открыть Chrome (incognito удобнее):

| # | URL | Что проверить |
|---|-----|---------------|
| 1 | http://saas.local/ | Traklo Pro landing, кнопка «Демо-доступ» |
| 2 | http://saas.local/demo/signup | Форма регистрации trial |
| 3 | после submit | Редirect на login, flash «Демо-доступ создан» |
| 4 | Mail/SMTP или лог | Письмо с временным паролем (если SMTP настроен) |
| 5 | http://saas.local/login | Вход новым admin |
| 6 | /onboarding | Мастер: компания, timezone → dashboard |
| 7 | /leads, /contractors | CRM доступен (Start) |
| 8 | /mail | 403 на Start (если mail не включён) |

## 4. Browser — Platform admin

| # | URL | Что проверить |
|---|-----|---------------|
| 1 | http://platform.saas.local/login | Вход `platform-admin@saas.local` / password (из seed) |
| 2 | /tenants | Список арендаторов, создание trial tenant |
| 3 | /plans | Матрица модулей, edit Start → лимиты сохраняются |
| 4 | /audit | Записи `tenant.demo_signup`, `tenant.created`, `plan.updated` |
| 5 | tenant → «Оплачено» + PDF | Счёт открывается (Gotenberg на http://127.0.0.1:3000) |

## 5. Если что-то ломается

| Симптом | Решение |
|---------|---------|
| 404 на `/` | `apply-saas-lab-env.ps1`, `SHOWCASE_MODE=traklo_pro` |
| 419 CSRF на login | `SESSION_SECURE_COOKIE=false` в `.env`, `php artisan config:clear` |
| Demo signup 404 | `SAAS_DEMO_SIGNUP_ENABLED=true` |
| Platform 403 | email в `SAAS_PLATFORM_ADMIN_EMAILS` |
| PDF не открывается | Gotenberg запущен, `GOTENBERG_URL` в `.env` |

## 6. Чеклист для галочек

Полный список: `docs/sync/pilot-smoke-checklist.md`

После прохода отметьте пункты в checklist и сохраните дату в `docs/sync/pilot-run-report-*.md` (опционально).
