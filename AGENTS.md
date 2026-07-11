# AGENTS.md — SaaS CRM для экспедиторов

## Проект

Vertical SaaS: CRM для экспедиторских компаний. Форк/эволюция внутренней CRM v5 (Laravel 13 + Inertia Vue 3).

**Не путать с v5.local** — тот репозиторий остаётся внутренним продуктом Автоальянса. SaaS — отдельный репозиторий с multi-tenancy.

## Стек (целевой, из v5)

- PHP 8.3, Laravel 13
- Inertia v2, Vue 3, Tailwind 3, Vite
- MySQL (single DB + `tenant_id`)
- Sanctum (API/MCP), Breeze (auth)
- Gotenberg (DOCX→PDF), PHPWord

## Структура репозитория

```
saas.local/
├── .cursor/
│   ├── agents/saas-architect.md   # субагент-архитектор
│   └── rules/                     # правила Cursor
├── docs/
│   ├── sync/                      # канон → Yandex Disk Exchange/saas
│   └── architecture/              # видение, фазы, ADR
├── scripts/
│   ├── sync-docs-to-yandex.ps1
│   └── bootstrap-from-v5.ps1      # Phase 1: копирование кода из v5
└── (код приложения — после bootstrap)
```

## Домен SaaS (что строим)

### ICP

Российские экспедиторы, 5–50 сотрудников. Нужны: лиды → заказы → документы → график оплат.

### Дифференциатор

Не «ещё одна CRM», а **логистический vertical**: маршрут/груз/перевозчик, график оплат, DOCX-печать, KPI продавцов.

### Тарифы (черновик)

| Тариф | Включено |
| --- | --- |
| **Start** | Лиды, заказы, контрагенты, задачи, RBAC, 5 пользователей |
| **Pro** | + документы, график оплат, печать, почта, скрипты, MCP read |
| **Enterprise** | + управленка, биржа, интеграции, custom domain |

### Tenancy

- Модель: `Tenant` + `tenant_id` на бизнес-таблицах
- Идентификация: subdomain (`acme.saas.local` / `acme.crm.ru`)
- Tenant #1 = текущий prod Автоальянс (миграция данных)
- Файлы: `storage/tenants/{id}/`
- Global scopes — **ни один query без tenant**

### Что переиспользуем из v5

| Паттерн | Файлы v5 |
| --- | --- |
| RBAC areas | `app/Support/RoleAccess.php` |
| Record scope | `OrderViewAuthorization`, `LeadViewAuthorization` |
| Service layer | `*Service.php` в `app/Services/` |
| Presenter pattern | Load Board, Order Wizard presenters |
| Print pipeline | `OrderPrintFormDraftService`, `PrintFormPlaceholderPathResolver` |
| Grid saved views | `GridViewService`, `GridViewCatalog` |
| Payment schedules | `OrderCompensationService`, `installments[]` JSON |

### Что НЕ входит в MVP (6 мес)

- Traklo / mobile external users
- Управленческий учёт + бюджетирование
- Биржа грузов / закупка
- OSINT / служба безопасности
- White-label fork на клиента
- Custom domain для всех

## Исходный CRM — справка

| Тема | Путь |
| --- | --- |
| Домен v5 | `C:\OSPanel\home\v5.local\AGENTS.md` |
| SaaS черновик | `C:\OSPanel\home\v5.local\docs\saas-roadmap.md` |
| Архитектура | `C:\Sync\Yandex.Disk\Exchange\CRM\v5-local\00-index.md` |
| Code audit | `Exchange/CRM/v5-local/Components/Code Audit 2026-07.md` |

## Сессия Cursor

1. `git pull`
2. Читать `docs/sync/Cursor-handoff-latest.md`
3. Читать `docs/sync/architecture-plan.md`
4. Для архитектуры — делегировать `saas-architect`
5. После работы — обновить handoff + `sync-docs-to-yandex.ps1`

## Команды

```powershell
# Синхронизация документации в vault
pwsh -File scripts/sync-docs-to-yandex.ps1

# Bootstrap кода из v5 (Phase 1, когда готовы)
pwsh -File scripts/bootstrap-from-v5.ps1 -WhatIf
```

## ADR

Архитектурные решения: `docs/architecture/decisions/ADR-*.md`

## Cursor Cloud specific instructions

Repo state (Phase 0): this is a **docs/planning + automation-scripts repository only** — there is
no Laravel application code yet (`composer.json`, `package.json`, `artisan`, `app/` do not exist).
The Laravel stack listed above is the *target* and gets bootstrapped later from `v5.local`, which is
a local Windows checkout **not available in the cloud VM**.

- The only runnable code is the two PowerShell scripts in `scripts/`. The update script installs
  `pwsh` (PowerShell 7) so they can run on Linux. No package-manager dependencies exist to install.
- There is no build/lint/test tooling. The closest "lint" is a syntax check via the PowerShell
  parser: `pwsh -NoProfile -Command 'Get-ChildItem scripts/*.ps1 | ForEach-Object { $e=$null; [void][System.Management.Automation.Language.Parser]::ParseFile($_.FullName,[ref]$null,[ref]$e); if($e){$e} }'`
- Both scripts default to **Windows paths** (`C:\...`). On Linux/cloud you must override them:
  - `pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot /tmp/vault` — syncs `docs/` into an
    `Exchange/saas/` vault tree. Runs fully cross-platform.
  - `pwsh -File scripts/bootstrap-from-v5.ps1 -V5Root <path> -WhatIf` — preview only. Without an
    actual `v5.local` source it errors (`$ErrorActionPreference='Stop'`), so cloud agents can only
    validate the `-WhatIf` dry run, not a real bootstrap.
