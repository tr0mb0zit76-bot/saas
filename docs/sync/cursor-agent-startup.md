# Cursor — старт сессии SaaS CRM

> **Канон в git:** `docs/sync/cursor-agent-startup.md`  
> **Копия на Я.Диске:** `Exchange/saas/cursor-agent-startup.md`  
> **Синхронизация:** `pwsh -File scripts/sync-docs-to-yandex.ps1`

---

## Зачем

- Код и документация SaaS — в **git** (`saas.git`).
- Obsidian vault на **Я.Диске** (`Exchange/saas/`) — для второго ПК; обновляется **из git**.
- Исходный CRM v5 — **read-only** справка, не смешивать с SaaS без явного решения.

---

## Первый ПК (основной)

### В начале сессии

1. `git pull` в `saas.local`
2. Агент читает:
   - `docs/sync/Cursor-handoff-latest.md`
   - `docs/sync/architecture-plan.md`
   - `AGENTS.md`
3. Для архитектуры — субагент `saas-architect`

### В конце сессии

1. Обновить `docs/sync/Cursor-handoff-latest.md`
2. `pwsh -File scripts/sync-docs-to-yandex.ps1`

---

## Второй ПК

### Один раз

```powershell
git clone https://github.com/tr0mb0zit76-bot/saas.git C:\OSPanel\home\saas\saas.local
```

### Каждая сессия

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull
pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "C:\Sync\Yandex.Disk\Exchange"
```

Читать handoff → architecture-plan → AGENTS.md.

---

## ОТДАТЬ / ЗАБРАТЬ

| Команда | Когда | Действие агента |
| --- | --- | --- |
| **ЗАБРАТЬ** | Старт на другом ПК | pull + sync + читать handoff |
| **ОТДАТЬ** | Конец сессии | обновить handoff + sync |

---

## Phase 1: bootstrap кода

Когда готовы к коду:

```powershell
pwsh -File scripts/bootstrap-from-v5.ps1 -WhatIf   # preview
pwsh -File scripts/bootstrap-from-v5.ps1            # execute
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Исходник: `C:\OSPanel\home\v5.local`.

---

## Справка: исходный CRM

| Тема | Путь |
| --- | --- |
| Код v5 | `C:\OSPanel\home\v5.local` |
| Vault CRM | `C:\Sync\Yandex.Disk\Exchange\CRM` |
| SaaS черновик в v5 | `v5.local/docs/saas-roadmap.md` |

Не вносить изменения в v5.local из SaaS-сессий без явного запроса.
