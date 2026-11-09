# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-11-09 · **Фаза:** Phase 4 + carrier matching P0 · **Ветка:** `cursor/traklo-session-integrate-9d42`

## Orientum «Пользователи» HTTP 503 (2026-11-09)

**Не баг Users/RBAC.** Access log: `GET /settings/users` → **503** at 15:55:10/22 (+0300), body ~6882 B, referrer `/leads` — окно `artisan down` в `deploy-prod.sh` (worker restart 15:56). Kernel/tinker на prod: authenticated admin → **200**, schema/RBAC OK (`admin@orientum.local`, role_id=5).

**Сопутствующий код-фикс:** `UserManagementController` раньше делал `get(['id','name','display_name'])` и читал `has_signing_authority` → всегда `false` для default в форме. Теперь колонка выбирается с `Schema::hasColumn` (как Roles). Тест: `tests/Feature/Saas/TenantUsersPageTest.php`.

**Smoke:** https://orientum.traklo.pro/settings/users (под `admin@orientum.local`).

## Platform 403 after login fix (2026-11-09)

**Причина:** `SESSION_DOMAIN=.traklo.pro` шарит cookie CRM-сессии на `platform.traklo.pro`. Authenticated non-admin попадал в `EnsurePlatformAdmin` → dead 403 «Platform administrator access required.» (после 38c0b66 guest→users redirect на portal home усиливал стену).

**Кто admin:** email allowlist `SAAS_PLATFORM_ADMIN_EMAILS` (`PlatformAdmin::isPlatformAdmin`). Prod: `platform-admin@traklo.pro` (user id=4).

**Фикс:** non-admin на portal → logout + redirect `/login` с ошибкой (JSON остаётся 403). Тест: `PlatformPortalTest::test_authenticated_non_platform_user_is_logged_out_to_login_not_403`.

**Вход:** https://platform.traklo.pro/login под email из allowlist (после CRM-сессии сначала откроется login с сообщением).

## Platform login 404 fix (2026-11-09)

**Причина:** `RedirectIfAuthenticated` по умолчанию вёл на CRM `route('dashboard')`; на `platform.traklo.pro` это ловил `AbortCrmOnPlatformHost` → 404.

**Фикс:** `bootstrap/app.php` `redirectTo(guests+users)` → platform home; alias `GET /dashboard` на portal. Commit `38c0b66`, задеплоено. Tenant login не трогали.

## Security P0 fixes (2026-11-09)

Закрыты C1/C2/C3 (+H2/H5): fleet/driver docs → `TenantStorage` (не `public`); customer `parseCargoRequest` heuristic-only (`TransportTextIntakeService::canUseLlm` fail-closed for externals); production apex/www → 404 без `SAAS_DEFAULT_TENANT_SLUG`; `MCP_DEV_USER_ID` только local/testing; carrier DTO без `customer_name`.

Тесты: `tests/Feature/Saas/SecurityP0IsolationTest.php` — **8 passed**.

## Security audit (full) — 2026-11-09

**Вердикт:** внутренний пилот на **одном** tenant — условно OK; **вторая внешняя компания на том же хосте — NOT pilot-safe** до P0 (см. фиксы выше).

Полный отчёт — в чате security-audit (saas-security). Смежные: privacy [PII/prompts](f9e30b89-7c03-478a-a359-9e6fc87a188a) · qa [Saas test gates](54a7e7c8-a699-4706-9580-61433ceb8c78).

## Platform packages = SubscriptionPlan features (2026-11-09)

«Пакеты» = тарифы `subscription_plans` (start/pro/enterprise). Состав модулей правится чекбоксами без кода:

- UI: `platform.*/plans` → «Модули» → `/plans/{key}/features`
- Persist: `SubscriptionPlan.features` (+ limits); gating: `Tenant::featureEnabled` ← plan DB (+ optional `settings.features` override)
- Каталог лейблов: `config/saas-features.php`; дефолты seed: `config/saas-plans.php`
- Harden: `SubscriptionPlanSeeder` больше **не** затирает features/limits при re-seed (только create + label/sort)
- Tests: `tests/Feature/Saas/PlatformPlanFeaturesTest.php` (5 passed)

## Архитектура просчёта — ADR-018 **Accepted** + MVP slice shipped (2026-11-09)

Канон: [`docs/architecture/decisions/ADR-018-client-quote-lifecycle.md`](../architecture/decisions/ADR-018-client-quote-lifecycle.md). Amends ADR-017.

**PO locks:** `target_price` = ориентир клиента; оферта = `quoted_price` + `quoted_currency` / `quote_status` / timestamps. Staff draft → send («Ответить клиенту») → agree («Цена согласована») → Order. External carriers — later.

**Код (MVP slice):**
- Migration `quoted_*` / `quote_*` on `leads`; Lead fillable/casts
- Staff API: `leads.carrier-matching.quote.{save,send,agree}` + finance tab UI
- `presentLeadForCustomer`: amount only when `sent`/`agreed`; never draft / cost / bids
- Messenger «Просчёты» shows sent/agreed quote
- `LeadConversionService` fail-closed: customer-request (or any started quote) needs `agreed`
- Tests: `tests/Feature/Saas/ClientLeadQuoteLifecycleTest.php` (**6 passed**)

**Later:** external carrier invite + minimized DTO; staff heuristic hint into draft.

**Вердикты (ask #2):** security [carrier quote isolation](c95f7d2d-c909-4939-9e49-ba92353002bd) · qa [quote flow gates](f53d5176-26cb-4346-a3a8-c6f73be059b5) · privacy [board stats 152-ФЗ](b836468e-152c-47d5-b472-d39546396c93).

## Orientum prod URL fix (2026-11-08)

Tenant `orientum` (id=2) на `orientum.traklo.pro`: корень → `/login` (302), login 200.  
Причина ассетов на apex: после `config:cache` runtime `env('FORCE_HTTPS')` пустой → не срабатывал `forceRootUrl(request host)`.  
Фикс: `config('app.force_https')` + ключ в `config/app.php`; hotfix на prod + `config:cache`. Link preload теперь `https://orientum.traklo.pro/build/...`.

Логин: `admin@orientum.local` (пароль в vault/seed заметках сессии).

**Orientum = наша компания (2026-11-08):** tenant id=2 переведён на `enterprise`; `settings.features` = полный каталог (28/28, включая `carrier_matching`, `load_board`, `fleet`, `mcp_write`, управленку и т.д.); `subscription_plans` на prod синхронизированы из `config/saas-plans.php`.

**Traklo invite / контакты (2026-11-08):** роли `counterparty_customer|carrier` сидятся per-tenant в `TenantProvisioner` (на Orientum уже есть). Блок приглашения перенесён с «Портрет» на вкладку «Контакты» (аккордеон + бейдж Traklo). Sync контактов больше не delete-all — upsert по `id`. Лиды из кабинета: `source = traklo_customer_request · {ФИО контакта}`.

**Кабинет заказчика — просчёты (2026-11-08):** вкладка «Просчёты» в `Messenger.vue` для `external_party=customer` при фиче `carrier_matching`: полная форма (маршрут, вес, объём, Д×Ш×В, места, упаковка, опасный груз) + parse из текста/скана (`POST …/cargo-requests/parse` → draft, без создания лида) + **редактирование** своего просчёта (`PUT …/cargo-requests/{lead}`). Создание = только Lead, **без** авто-публикации на Биржу (`ensureBoardPostForLead` только из `shareLeadWithCarriers`). Share на matching-биржу тащит package_count / hazardous / габариты.

## Carrier matching P0 (2026-11-08)

ADR-017 **Accepted**. Флаг `carrier_matching` на Start/Pro/Enterprise (отдельно от Enterprise `load_board`).

Поток: клиентский кабинет → Lead (`traklo_customer_request`) → staff share выбранным перевозчикам tenant → ставка в кабинете перевозчика → accept пишет в `Lead.performers` **без Order**.

Код: `CarrierMatchingService`, `LeadCarrierMatchingController`, `MobileCarrierMatchingController`.  
Тесты: `tests/Feature/Saas/ClientLeadCarrierBidIsolationTest.php`.

Ещё нет: Vue UI staff share / кабинет перевозчика (ставки), профиль маршрутов перевозчика (follow-on).

## Prod CD (2026-11-08)

- Workflow: `.github/workflows/deploy-prod.yml` → self-hosted runner `traklo-prod` на VPS (systemd).
- GitHub-hosted SSH в prod не проходит (ISPmanager firewall).
- Канон: `docs/sync/prod-git-deploy.md`. Push в integrate/`main` деплоит через `scripts/deploy-prod.sh`.
- Smoke: run `42484520839` success, `/up` HTTP 200.

## Команда архитектора (2026-10-26)

Роли в `.cursor/agents/`: `saas-architect`, `saas-security`, `saas-qa`, `saas-privacy`. Staging-хост — `saas-migration-orchestrator`.

Изоляция, которую закрыли в коде: сырой SQL выручки фильтрует `tenant_id`; CRM на platform-хосте отдаёт 404 и больше не включает bypass; корень URL платформы сбрасывается после запроса; модели с колонкой `tenant_id` получили `BelongsToTenant`; журнал AI и блок-лист почты режутся по арендатору; тариф Start не открывает бюджет, диспозицию, план компании, «сколько влезет», mobile shell и AI-аналитику; сканы водителя пишутся в хранилище арендатора; экспорт содержит людей, `saas` стирает данные одного арендатора через `TenantErasureService`. `php artisan test tests/Feature/Saas` — **58 passed** (2026-10-26, БД `127.0.1.12` / `saas_crm_test`).

Не из кода: VM/DNS/TLS staging, оферта и решение «где хостинг».

---

## План доработок (product) — 2026-10-15

Канон: [`docs/architecture/saas-refinement-backlog.md`](../architecture/saas-refinement-backlog.md)

**Сделано в коде (автономно):**
- ADR-015/016; `SAAS_LEGACY_AA_INTEGRATIONS` → 404 на Astral/1C-Fresh  
- `feature:print` на шаблоны + меню; вкладка **Свои плейсхолдеры** (allowlist)  
- Колонки «Внесено в 1С» / ЭТрН скрыты без `feature:integrations`  
- Гайды: `docs/sync/server-deploy-guide.md`, `docs/sync/tenant-admin-crm-launch.md`

**Ещё не сделано:** WS-5 брендинг/онбординг polish, WS-7 doc checklist, WS-8 v5 ports, полный strip классов EPD (маршруты уже карантин).

---

## ОТДАТЬ — staging readiness (2026-10-15)

### Verified locally

| Check | Result |
| --- | --- |
| `git pull` | Already up to date on `cursor/traklo-session-integrate-9d42` |
| `repair-lab-after-pull.ps1` | Env/cache OK; **MySQL provision failed** (port 3306 closed) |
| `migration-status.ps1` | Probes OK: artisan, vendor, .env, node_modules, public/build |
| `php artisan about` | Traklo Pro / Laravel 13.4 / PHP 8.3 / APP_ENV=local |
| `php artisan test tests/Feature/Saas` | **58 passed** (2026-10-26, `127.0.1.12` / `saas_crm_test`) |
| ForceLabHttpSessionCookies | **OK** — early return unless `local`/`testing` |
| Re-bootstrap from v5 | **Not done** (AA 1C/EPD out of staging scope) |

### Staging artifacts (local prep done — P4.2a–c)

- `docs/architecture/decisions/ADR-014-staging-topology.md` (Proposed)
- `.env.staging.example` — `APP_ENV=production`, HTTPS, Redis queue/cache, `tenant_s3`, CSP on, demo signup off
- `docs/sync/staging-checklist.md`
- `scripts/deploy-staging.sh` (+ `storage:link`)
- `ForceLabHttpSessionCookies` gated to lab only (ADR-014)

### Human blockers (true escalations)

1. **Start OSPanel MySQL** on lab machine — needed for local SaaS feature tests / repair seed.
2. **Staging host:** Linux VM + SSH, wildcard DNS `*.{CRM_DOMAIN}`, TLS, secrets (`APP_KEY`, DB, Redis, S3, SMTP), accept ADR-014, demo-signup policy.

### Combat-test bar (do not expand scope)

Single Linux VM · HTTPS · Redis+queue+scheduler · Gotenberg · `TENANT_STORAGE_DISK=tenant_s3` · nightly `saas:backup-database`.  
**Later:** 2FA, full monitoring, queue-migrate.  
**Skip for staging:** v5 1C/EPD/Load Board/intercompany/MCP portfolio.

---

## Staging / боевой тестовый

**Deploy on server (after secrets + DNS):**

```bash
cp .env.staging.example .env   # fill secrets
php artisan key:generate
bash scripts/deploy-staging.sh main
# optional demo:
php artisan db:seed --class=SaasDemoSeeder --force
```

Then: Supervisor `queue:work redis`, cron `schedule:run`, nightly backup, HTTPS smoke → clear **M9.5**.

---

## Lab (home-pc)

```powershell
git pull origin cursor/traklo-session-integrate-9d42
# start OSPanel MySQL first
pwsh -File scripts/repair-lab-after-pull.ps1
npm run build
php artisan test tests/Feature/Saas --compact
```

```env
APP_URL=http://saas.local
CRM_DOMAIN=saas.local
DB_HOST=127.0.1.12
DB_DATABASE=saas_crm
APP_NAME="Traklo Pro"
SESSION_SECURE_COOKIE=false
SHOWCASE_MODE=traklo_pro
SAAS_DEMO_SIGNUP_ENABLED=true
SAAS_DEFAULT_TENANT_SLUG=demo
PLATFORM_DOMAIN=platform.saas.local
TENANT_STORAGE_DISK=tenant_local
```

**Demo:** `admin@saas.local` / `password` (after seed)  
**Do not commit:** `cookies.txt`

---

## Prior session notes (Traklo skin / lab 419)

Still relevant for lab UX smoke:

- CSS cascade + agGrid `workspace_skin` + ForceLabHttp (lab HTTP) + CSRF retry — see git history `5774975`
- Browser check: `dataset.crmWorkspaceSkin === "traklo"` after save+F5; PATCH 303 not 419

---

## M9–P4 status

| Step | Status |
| --- | --- |
| M9–M12, P4.1 | done |
| P4.2a–c staging prep files + cookie gate | **done** |
| P4.2d–e host provision + first deploy | **pending (human)** |
| M9.5 browser smoke | **pending** until HTTPS staging |
| P4 remainder | 2FA, monitoring, queue-migrate |

---

## Следующие шаги

### Human
1. Confirm OSPanel MySQL is running (lab tests).
2. Provision staging VM + wildcard DNS + TLS + fill `.env` from `.env.staging.example`.
3. Run `bash scripts/deploy-staging.sh main` and HTTPS smoke checklist.

### Next agent turn
1. After MySQL up: re-run `repair-lab-after-pull.ps1` + `php artisan test tests/Feature/Saas`.
2. After human host ready: assist first deploy / document results; mark P4.2d–e + M9.5 when HTTPS smoke passes.
3. Do **not** full bootstrap from v5; do **not** commit `cookies.txt`.
4. Merge integrate PR → `main` when CI green (CI has its own MySQL service).
