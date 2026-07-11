# Cursor handoff — SaaS CRM (экспедиторы)

> **Синхронизация:** Yandex Disk `Exchange/saas/` · **Код:** `git pull` в `saas.local`  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-07-11 · **Ветка:** `main` · **Фаза:** 0 (продуктовая гипотеза → подготовка Phase 1)

---

## Итог сессии 2026-07-11 — инициализация SaaS проекта

| Блок | Статус |
| --- | --- |
| Анализ CRM v5 + Exchange/CRM | ✅ |
| Архитектурный план (`architecture-plan.md`) | ✅ |
| Субагент `saas-architect` | ✅ `.cursor/agents/saas-architect.md` |
| Репозиторий `saas.git` | ✅ инициализирован локально |
| Документация vault `Exchange/saas` | ✅ структура + sync script |
| Dev-окружение OSPanel `saas.local` | ✅ PHP 8.3, ждёт bootstrap кода |
| ADR (3 решения) | ✅ repo strategy, tenant isolation, module packaging |

**Код приложения:** ещё не скопирован из v5 — Phase 1 kickoff через `scripts/bootstrap-from-v5.ps1`.

---

## Следующая сессия

1. Найти 2–3 пилотных экспедитора (человек)
2. Юридическое: оферта, 152-ФЗ (человек)
3. Spike: скрипт таблиц для `tenant_id` по миграциям v5
4. Phase 1: `bootstrap-from-v5.ps1` → `composer install` → миграция `tenants`
5. Первый isolation test

---

## Ключевые пути

| Что | Где |
| --- | --- |
| Код | `C:\OSPanel\home\saas\saas.local` |
| Git | https://github.com/tr0mb0zit76-bot/saas.git |
| Vault | `C:\Sync\Yandex.Disk\Exchange\saas` |
| Исходный CRM | `C:\OSPanel\home\v5.local` |
| Архитектурный план | `docs/sync/architecture-plan.md` |
| Субагент | `saas-architect` |

---

## Между ПК

Напиши агенту **ОТДАТЬ** (конец сессии) или **ЗАБРАТЬ** (старт) — см. `docs/sync/cursor-agent-startup.md`.

### ЗАБРАТЬ

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull
pwsh -File scripts/sync-docs-to-yandex.ps1
```

Читать: этот handoff → `architecture-plan.md` → `AGENTS.md`.

### ОТДАТЬ

1. Обновить этот файл (дата, что сделано, следующий шаг)
2. `pwsh -File scripts/sync-docs-to-yandex.ps1`
3. `git add -A && git commit && git push` (если просили коммит)
