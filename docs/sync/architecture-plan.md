# Архитектурный план: CRM v5 → SaaS для экспедиторов

> **Статус:** утверждённый план Phase 0 (2026-07-11)  
> **Автор:** анализ CRM v5 + субагент `saas-architect`  
> **Исходники:** `v5.local`, `Exchange/CRM`, `docs/saas-roadmap.md`

---

## Executive summary

CRM v5 — зрелый **single-tenant vertical product** (~120 Eloquent-моделей, Laravel 13 + Inertia Vue 3) для логистической компании. Переход к SaaS — не «добавить регистрацию», а **multi-tenant продукт + биллинг + изоляция + модульная упаковка**.

**Стратегия:** форк v5 в отдельный репозиторий `saas.git`, добавить `tenant_id`, tenant #1 = Автоальянс, упаковать модули по тарифам.

**Срок до первых paying customers:** 6–9 месяцев (1–2 разработчика + доменная экспертиза).

**ICP:** российские экспедиторы, 5–50 сотрудников.

---

## 1. Анализ исходного CRM

### 1.1 Технологический стек

| Слой | Технологии |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Eloquent, Sanctum, Laravel MCP |
| Frontend | Inertia v2, Vue 3, Tailwind 3, Vite, AG Grid |
| DB | MySQL, single database, **без tenant_id** |
| Auth | Breeze (session) + Sanctum (MCP/mobile) |
| Print | PHPWord → Gotenberg → PDF, QR verify |
| AI | Command Bar, AgentToolRegistry, 36 MCP tools |
| Push | ntfy sidecar + FCM (Traklo) |

### 1.2 Бизнес-модули (граф зависимостей)

```
RBAC (visibility_areas + scopes)
    │
    ├── Leads ──► Orders ──► Documents ──► Finance (payment_schedules)
    │       │         │           │              │
    │       │         ├─ Fleet    │              ├─ Management Accounting
    │       │         ├─ Disposition             └─ Budgeting
    │       │         ├─ Load Board
    │       │         └─ Mail
    │       └── Tasks/Kanban
    │
    ├── Contractors (DaData/Checko scoring)
    ├── Sales Assistant (Scripts, Book, Trainer)
    ├── Utility Modules (Loading Planner, Import Cost)
    └── Settings (templates, KPI, MCP links)
```

### 1.3 Что универсально vs специфично для AA

| Универсально (SaaS core) | Специфично (per-tenant config) |
| --- | --- |
| Lead → Order воронка | `own_company_id` — юрлица экспедитора |
| Order wizard (маршрут, груз, финансы) | Нумерация заказов per own company |
| Контрагенты (заказчик/перевозчик) | Шаблоны DOCX per own company |
| График оплат (installments JSON) | Бизнес-процесс продаж (playbooks) |
| Чек-лист документов | Книга продаж (контент) |
| RBAC areas/scopes | KPI/мотивация |
| Grid saved views | Растаможка (РФ-only) |
| Sales scripts framework | Биржа/закупка (AA workflow) |
| Fleet trips | Traklo branding |

### 1.4 Существующие multi-company паттерны (внутри tenant)

- **`own_company_id`** — несколько юрлиц одного экспедитора (`contractors.is_own_company`)
- **`departments`** — подразделения с approval routing
- **`visibility_scopes`** — `own` / `department` / `all` на записях

Эти паттерны **не заменяют tenancy**, но хорошо ложатся на per-tenant конфигурацию.

### 1.5 Сильные стороны (сохранить)

- Сервисный слой (payment schedules, print, management accounting)
- RBAC: `RoleAccess` + `visibility_areas` + `*ViewAuthorization`
- Presenter/Advisor pattern (Load Board — образец декомпозиции)
- Тесты: payment schedules, print forms, RoleAccess

### 1.6 Технический долг (закрыть до/during tenancy)

| Приоритет | Проблема | Файлы |
| --- | --- | --- |
| P0.10 | Остаточный IDOR по `manager_id` | `FinanceOverviewService`, `PaymentScheduleAutomaticStatus` |
| P1.1 | `department` scope не везде в backend | `LeadAttentionQueueService`, finance journal |
| P2.1 | Order Wizard ~3140 строк Vue | `Wizard.vue` + composables |

**Критично:** scope leaks усиливаются при multi-tenant — закрыть P0.10/P1.1 в Phase 1.

---

## 2. Целевая архитектура SaaS

### 2.1 Tenancy model

```
┌─────────────────────────────────────────────────┐
│                  Platform Layer                  │
│  super-admin · billing · tenant registry · CI   │
└─────────────────────┬───────────────────────────┘
                      │
        ┌─────────────┼─────────────┐
        ▼             ▼             ▼
   Tenant A       Tenant B       Tenant C
   (AA #1)        (pilot)        (pilot)
        │             │             │
   subdomain      subdomain      subdomain
   aa.crm.ru      beta.crm.ru    gamma.crm.ru
        │             │             │
   own_companies  own_companies  own_companies
   departments    departments    departments
   users/roles    users/roles    users/roles
   orders/leads   orders/leads   orders/leads
```

**Решение:** single database + `tenant_id` на бизнес-таблицах (до 50–200 клиентов).  
**Альтернатива (enterprise):** schema-per-tenant — позже.

### 2.2 Идентификация tenant

1. **Subdomain** (MVP): `{slug}.crm.ru` / `{slug}.saas.local`
2. **Custom domain** (Enterprise): CNAME → platform
3. Middleware `IdentifyTenant`: резолвит tenant из host → устанавливает контекст
4. Global scope `TenantScope` на все Tier A/B модели
5. Queue jobs: `tenant_id` в payload + `TenantContext::set()`

### 2.3 Хранение файлов

```
storage/tenants/{tenant_id}/
├── documents/
├── print-artifacts/
├── mail-attachments/
└── proposal-templates/
```

### 2.4 Super-admin

- Список tenants, suspend/activate
- Impersonate (только с audit log)
- Usage metrics (users, orders/month, storage)
- Billing status

---

## 3. Модульная упаковка (тарифы)

### Start (базовый)

| Модуль | Описание |
| --- | --- |
| Auth + RBAC | `visibility_areas`, роли, 5 users |
| Leads | Воронка, упрощённый playbook |
| Orders | Мастер заказа (core tabs) |
| Contractors | Справочник контрагентов |
| Tasks | Kanban + SLA basic |
| Grid + saved views | AG Grid |
| 1 subdomain | `{slug}.crm.ru` |

**Лимиты:** 5 users, 100 orders/month, 1 GB storage.

### Pro

| Модуль | Описание |
| --- | --- |
| Start + | |
| Documents | Упрощённый чек-лист (не полный регламент AA) |
| Payment schedules | График оплат, installments |
| Print DOCX/PDF | Шаблоны per tenant, QR verify |
| Mail IMAP | Синхронизация почты |
| Sales scripts + Book | Контент per tenant |
| HTML proposals | GrapesJS шаблоны |
| MCP read | AI assistant (read-only tools) |

**Лимиты:** 25 users, 500 orders/month, 10 GB.

### Enterprise

| Модуль | Описание |
| --- | --- |
| Pro + | |
| Management accounting | Управленка + бюджет |
| Load Board | Биржа/закупка |
| Integrations | 1C, Astral EPD |
| Import cost calculator | РФ растаможка |
| Custom domain | CNAME |
| Traklo mobile | Отдельный SKU |

---

## 4. Фазы реализации

### Фаза 0 — продуктовая гипотеза (~1 мес, без кода)

**Цель:** понять что сдаём и кому.

| Артефакт | Статус |
| --- | --- |
| ICP one-pager | ✅ этот план |
| 3 тарифа (Start/Pro/Enterprise) | ✅ §3 |
| Решение: Traklo в base? | ✅ только Enterprise SKU |
| Юридическое: оферта, 152-ФЗ | ⏳ |
| 2–3 пилотных компании | ⏳ до Phase 1 |

### Фаза 1 — tenancy и изоляция (2–3 мес)

**Цель:** `tenant_id` + изоляция + tenant #1 = AA.

| Задача | Детали |
| --- | --- |
| Bootstrap из v5 | `scripts/bootstrap-from-v5.ps1` |
| Модель `Tenant` | slug, name, status, plan, settings JSON |
| `tenant_id` на Tier A/B таблицах | ~80 таблиц (см. §5) |
| `IdentifyTenant` middleware | subdomain resolution |
| `TenantScope` global scope | на все бизнес-модели |
| Storage isolation | `storage/tenants/{id}/` |
| Queue tenant context | job middleware |
| Backfill AA | `tenant_id = 1` на все существующие записи |
| Isolation tests | tenant A ≠ tenant B (обязательно) |
| Super-admin shell | список tenants, suspend |
| Close P0.10/P1.1 | RBAC scope gaps |

**Оценка:** 8–14 недель (1–2 senior).

### Фаза 2 — онбординг и биллинг (1–2 мес)

| Задача | Детали |
| --- | --- |
| Signup flow | email → tenant creation → wizard |
| Onboarding wizard | название, часовой пояс, валюта, own company |
| Trial 14 дней | auto-provision |
| Billing | ЮKassa / CloudPayments |
| Лимиты | users, orders/month, storage |
| Suspend при неоплате | read-only mode |
| Email | welcome, trial ending, invoice |

### Фаза 3 — MVP packaging (2–3 мес)

| Задача | Детали |
| --- | --- |
| Feature flags per plan | `tenant.features[]` |
| Упрощённые playbooks | default seed per tenant |
| Demo tenant | seed script |
| In-app help | ссылки на документацию |
| Landing page | продуктовый сайт |
| Pilot onboarding | 2–3 компании |

### Фаза 4 — security & ops (параллельно)

| Задача | Детали |
| --- | --- |
| Audit log | orders, money, roles changes |
| Backup/restore | daily + restore drill |
| CI/CD | pint, tests, build |
| Rate limits | API, signup |
| 2FA | tenant-admin |
| Runbook | onboarding ≤ 30 мин |
| Миграции | очередь, не ручной SSH |

---

## 5. Таблицы для `tenant_id`

### Tier A — бизнес-данные (обязательно)

`orders`, `order_legs`, `route_points`, `cargos`, `order_documents`, `payment_schedules`, `payment_schedule_payment_events`, `leads`, `lead_*`, `contractors`, `contractor_*`, `tasks`, `fleet_*`, `disposition_entries`, `mail_threads`, `mail_messages`, `activity_events`, `grid_views`, `conversations`, `chat_messages`, `external_user_invites`, `order_portal_invites`

### Tier B — конфигурация tenant

`users`, `roles`, `departments`, `department_user`, `business_processes`, `print_form_templates`, `proposal_html_templates`, `order_numbering_rules`, `sales_scripts` + graph tables, `sales_book_articles`, `transport_templates`, `loading_planner_projects`, `mcp_data_links`

### Tier C — platform (новые таблицы)

`tenants`, `tenant_subscriptions`, `tenant_usage_logs`, `tenant_audit_logs`

### Tier D — shared reference

`import_cost_tn_ved_entries`, `vat_rates` — global + tenant overrides

---

## 6. Стратегия репозитория

| Репозиторий | Назначение |
| --- | --- |
| `v5.git` (v5.local) | Внутренний CRM Автоальянс, prod |
| `saas.git` (saas.local) | Multi-tenant SaaS продукт |

**Workflow:**
1. Bootstrap: копия v5 → saas (один раз)
2. Далее: независимая разработка в saas
3. Bugfixes в v5: cherry-pick в saas по необходимости
4. Общие паттерны: документировать в ADR, не sync автоматически

---

## 7. Риски

| Риск | Митигация |
| --- | --- |
| Утечка данных между tenants | Isolation tests + code review + pentest |
| Prod AA сломан tenancy-рефакторингом | Tenant #1 first, feature flags |
| Поддержка съедает маржу | Жёсткий MVP, in-app help |
| Узкий домен для mass SaaS | Vertical «для экспедиторов» — осознанный выбор |
| Order Wizard техдолг | Не блокирует tenancy; рефакторинг параллельно |

---

## 8. Следующие шаги

### Немедленно (Phase 0 complete → Phase 1 prep)

1. ✅ Создать репозиторий `saas.git` и документацию
2. ✅ Субагент `saas-architect`
3. ⏳ Найти 2–3 пилотных экспедитора
4. ⏳ Юридическое: оферта, 152-ФЗ, хостинг
5. ⏳ Spike: скрипт списка таблиц для `tenant_id` (по миграциям v5)

### Phase 1 kickoff

1. `pwsh -File scripts/bootstrap-from-v5.ps1`
2. `composer install && npm ci`
3. Создать миграцию `tenants` + `tenant_id` на Tier A
4. `IdentifyTenant` middleware + `TenantScope`
5. Первый isolation test
6. Backfill tenant #1 = AA

---

## 9. Ссылки

| Документ | Путь |
| --- | --- |
| Видение | `docs/architecture/00-vision.md` |
| Исходный CRM | `docs/architecture/source-crm-reference.md` |
| ADR repo strategy | `docs/architecture/decisions/ADR-001-repo-strategy.md` |
| ADR tenant isolation | `docs/architecture/decisions/ADR-002-tenant-isolation.md` |
| ADR module packaging | `docs/architecture/decisions/ADR-003-module-packaging.md` |
| Черновик v5 | `v5.local/docs/saas-roadmap.md` |
| Code audit | `Exchange/CRM/v5-local/Components/Code Audit 2026-07.md` |

*Обновлено: 2026-07-11.*
