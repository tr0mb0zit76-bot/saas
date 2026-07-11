# Справка: исходный CRM v5

> Read-only. Не модифицировать v5.local из SaaS-сессий.

## Пути

| Ресурс | Путь |
| --- | --- |
| Код | `C:\OSPanel\home\v5.local` |
| Git | `tr0mb0zit76-bot/v5` |
| Vault | `C:\Sync\Yandex.Disk\Exchange\CRM` |
| Домен (код) | `AGENTS.md` → «Домен приложения» |
| Архитектура | `Exchange/CRM/v5-local/00-index.md` |

## Стек

PHP 8.3 · Laravel 13 · Inertia v2 · Vue 3 · Tailwind 3 · MySQL · Sanctum · MCP

## Ключевые модули для SaaS

| Модуль | v5 компонент | SaaS tier |
| --- | --- | --- |
| Leads | `Leads.md` | Start |
| Orders | `Orders.md` | Start |
| Contractors | `Contractors.md` | Start |
| Tasks | `Tasks and Kanban.md` | Start |
| RBAC | `Role Access and Visibility.md` | Start |
| Documents | `Documents.md` | Pro |
| Finance | `Finance.md` | Pro |
| Print | `Print Forms DOCX.md` | Pro |
| Sales Assistant | `Sales Assistant.md` | Pro |
| Management Accounting | `Management Accounting.md` | Enterprise |
| Fleet | `Fleet Own Fleet.md` | Pro (add-on) |
| Load Board | (handoff) | Enterprise |
| Traklo | `Interfaces/Carrier Portal.md` | Enterprise SKU |

## Multi-company паттерны (→ per-tenant config)

- `orders.own_company_id` — юрлицо экспедитора
- `contractors.is_own_company` — справочник «наших компаний»
- `departments` + `department_user` — подразделения
- `visibility_scopes` — own/department/all

## Технический долг (закрыть при tenancy)

См. `Exchange/CRM/v5-local/Components/Code Audit 2026-07.md`:
- P0.10: residual IDOR
- P1.1: department scope gaps

## Черновик SaaS в v5

`v5.local/docs/saas-roadmap.md` — исходный черновик (2026-07-09), канонизирован в `docs/sync/architecture-plan.md`.
