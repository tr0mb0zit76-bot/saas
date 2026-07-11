# SaaS CRM — навигация vault

> Vault: `Yandex.Disk/Exchange/saas` · Код: `C:/OSPanel/home/saas/saas.local` · Git: https://github.com/tr0mb0zit76-bot/saas.git  
> Источник в git: `docs/sync/00-index.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

## Cursor

- [[Cursor-handoff-latest|Handoff — актуальный контекст]] ← **читать первым**
- [[cursor-agent-startup|Старт сессии Cursor]]
- [[migration-runbook|Runbook переезда M0–M7]]
- [[migration-state.json|Состояние переезда (JSON)]]

## Субагенты

- **saas-migration-orchestrator** — автономный переезд (bootstrap, БД, migrate, smoke)
- **saas-architect** — архитектура, ADR, тарифы

## Архитектура

- [[architecture/plan|Архитектурный план SaaS]] ← **главный документ**
- [[architecture/00-vision|Видение продукта]]
- [[architecture/source-crm-reference|Справка: исходный CRM v5]]

### Фазы

- [[architecture/phases/00-product-hypothesis|Фаза 0 — продуктовая гипотеза]]
- [[architecture/phases/01-tenancy|Фаза 1 — tenancy и изоляция]]
- [[architecture/phases/02-billing|Фаза 2 — онбординг и биллинг]]
- [[architecture/phases/03-mvp-packaging|Фаза 3 — MVP для арендаторов]]
- [[architecture/phases/04-security-ops|Фаза 4 — безопасность и ops]]

### Решения (ADR)

- [[architecture/decisions/ADR-001-repo-strategy|ADR-001: стратегия репозитория]]
- [[architecture/decisions/ADR-002-tenant-isolation|ADR-002: изоляция tenant]]
- [[architecture/decisions/ADR-003-module-packaging|ADR-003: упаковка модулей]]

## Исходный CRM (read-only)

- [[../CRM/00-index|CRM vault — навигация]]
- [[../CRM/v5-local/00-index|Карта компонентов v5]]
- Черновик SaaS в v5: `v5.local/docs/saas-roadmap.md`

*Создано: 2026-07-11.*
