# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-12 · **Фаза:** M8 done → M9 prep · **Ветка:** `main`

---

## Platform login 419 (lab HTTP)

- **Не SSL как таковой:** на `http://platform.saas.local` cookies без `Secure`, пока `SESSION_SECURE_COOKIE=false` и `APP_URL=http://…`.
- **Реальная причина 419:** `trustProxies` + заголовок `X-Forwarded-Proto: https` → Symfony ставит `Secure` на session cookie → браузер на HTTP не отправляет cookie → CSRF 419.
- **Fix:** `configureLabHttpSessionCookies()` в `AppServiceProvider`, `SESSION_SECURE_COOKIE=false` в lab scripts, `ForcePlatformRootUrl` для Ziggy.
- **Cursor Simple Browser:** даже после fix может не сохранять HttpOnly cookies — для platform login используйте Chrome/Edge.

---

## Продукт: Traklo Pro

- **Pilot:** чистый demo-tenant
- **Billing:** счета / УПД вручную (ADR-009) — PDF счёта через Gotenberg
- **Storage:** S3 prod, `tenant_local` lab
- **Mobile:** один APK + subdomain

---

## Сделано (M8 в `main`)

### M8.1 Onboarding
- Platform create tenant: **admin user** (роль admin) + `TenantWelcomeMail` с временным паролем
- Поля формы: `admin_name`, `admin_email`, `send_invite`
- `TenantOnboardingService`, `TenantHost::url()` для login URL в письме

### M8.2 Usage limits
- `TenantUsageLimiter`: лимиты `users` и `orders_per_month` из тарифа
- Проверка при создании пользователя и заказа (ValidationException)

### M8.3 Invoice PDF
- `GET /platform/tenants/{tenant}/invoices/{invoice}/pdf`
- Blade-шаблон + Gotenberg (как LeadProposalPdfService)
- Кнопка PDF в списке арендаторов после «Оплачено»

### M8.4 Landing / login / pilot
- `SHOWCASE_MODE=traklo_pro` → TrakloLanding (уже было)
- TrakloLoginScene на `/login` (уже было)
- Чеклист: `docs/sync/pilot-smoke-checklist.md`

### M8.5 Pilot
- **Automated pilot:** `PilotSmokeTest` — PASS (2026-07-12)
- Отчёт: `docs/sync/pilot-run-report-2026-07-12.md`
- Manual browser smoke на home-pc — по `pilot-smoke-checklist.md`

### Ранее (M7)
- Platform super-admin, mail lazy attachments + AI retention, Vite chunking, Ponytail rules

### Tests — **28+ passed** (Saas + mail)

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
SHOWCASE_MODE=traklo_pro
TENANT_STORAGE_DISK=tenant_local
TENANT_STORAGE_FOR_DOCUMENTS=true
SAAS_DEFAULT_TENANT_SLUG=demo
SAAS_PLATFORM_ADMIN_EMAILS=admin@saas.local
SAAS_TRIAL_DAYS=14
DOC_PREVIEW_DRIVER=gotenberg
GOTENBERG_URL=http://127.0.0.1:3000
```

Login: `admin@saas.local` / `password`  
Platform: **Настройки → Platform Admin** или `/platform`

**Первый pilot:** см. `docs/sync/pilot-smoke-checklist.md`

---

## Следующие шаги (M9)

1. **Self-service signup** — регистрация без platform admin (Phase 2 architecture-plan)
2. **Onboarding wizard в CRM** — own company, timezone, первый контрагент
3. **Usage metering cron** — `tenant_usage_logs`, storage limits
4. **Suspend read-only mode** — при неоплате блок create/update
5. **ЮKassa webhook skeleton** — ADR-009 amendment (опционально)
6. **P0.10 / P1.1 audit** — если ещё не закрыто в prod paths
7. **Первый реальный внешний экспедитор** — browser smoke на home-pc

---

## От вас ничего не требуется

`git pull origin main` + `migrate` + `npm run build` на home-pc. Для PDF счетов — Gotenberg в lab.
