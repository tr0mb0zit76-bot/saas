# SaaS CRM для экспедиторов

Vertical B2B SaaS на базе внутренней CRM «Автоальянс» (v5.local). Предоставляет логистическую CRM любой экспедиторской компании — не только нашей.

## Репозитории и пути

| Что | Где |
| --- | --- |
| **Код SaaS** | `C:\OSPanel\home\saas\saas.local` |
| **Git** | https://github.com/tr0mb0zit76-bot/saas.git |
| **Документация (vault)** | `C:\Sync\Yandex.Disk\Exchange\saas` |
| **Исходный CRM (read-only)** | `C:\OSPanel\home\v5.local` |
| **Знания CRM (vault)** | `C:\Sync\Yandex.Disk\Exchange\CRM` |

## Быстрый старт

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull
pwsh -File scripts/sync-docs-to-yandex.ps1
```

Читать: `docs/sync/Cursor-handoff-latest.md` → `docs/sync/architecture-plan.md` → `AGENTS.md`.

## Субагенты

| Агент | Назначение |
| --- | --- |
| **`saas-migration-orchestrator`** | Автономный переезд v5 → saas |
| **`saas-architect`** | Архитектура и ADR |

Запуск переезда одной фразой: *«Продолжи переезд»*

```powershell
pwsh -File scripts/setup-lab.ps1
pwsh -File scripts/migration-status.ps1
```

## Фазы (кратко)

| Фаза | Срок | Результат |
| --- | --- | --- |
| 0 | ~1 мес | ICP, тарифы, scope MVP |
| 1 | 2–3 мес | `tenant_id`, изоляция, tenant #1 = AA |
| 2 | 1–2 мес | signup, trial, биллинг |
| 3 | 2–3 мес | MVP для внешних клиентов |
| 4 | параллельно | security, ops, CI |

Подробно: `docs/sync/architecture-plan.md`.

## OSPanel

- Домен: `saas.local`
- PHP 8.3, Apache
- `web_root = public` (появится после bootstrap из v5)

## Синхронизация документации

```powershell
pwsh -File scripts/sync-docs-to-yandex.ps1
```

Копирует `docs/sync/` → `Exchange/saas/`.
