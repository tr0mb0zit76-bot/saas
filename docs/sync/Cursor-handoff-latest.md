# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-11-08 · **Фаза:** Phase 4 + carrier matching P0 · **Ветка:** `cursor/traklo-session-integrate-9d42`

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
