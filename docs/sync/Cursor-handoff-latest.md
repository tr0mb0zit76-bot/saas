# Cursor handoff — SaaS CRM (экспедиторы)

> **Синхронизация:** Yandex Disk `Exchange/saas/` · **Код:** `git pull` в `saas.local`  
> **Оркестратор:** `saas-migration-orchestrator` · **Состояние:** `docs/sync/migration-state.json`

**Обновлено:** 2026-07-11 · **Ветка:** `main` · **Фаза переезда:** M0 (не начат)

---

## Итог сессии 2026-07-11 — оркестратор переезда

| Блок | Статус |
| --- | --- |
| Субагент `saas-migration-orchestrator` | ✅ `.cursor/agents/saas-migration-orchestrator.md` |
| Runbook M0–M7 | ✅ `docs/sync/migration-runbook.md` |
| State file | ✅ `docs/sync/migration-state.json` |
| Скрипты: setup-lab, provision-database, migration-status | ✅ |
| Audit remediation checklist | ✅ `docs/architecture/saas-audit-remediation.md` |

**Код CRM:** ещё не bootstrapped — оркестратор запускает `setup-lab.ps1` автономно.

---

## Как запустить переезд (человеку)

Одна фраза агенту:

> **«Продолжи переезд»** или **«Запусти saas-migration-orchestrator»**

Или вручную:

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull
pwsh -File scripts/setup-lab.ps1
pwsh -File scripts/migration-status.ps1
```

**Участие человека не нужно**, кроме blockers (MySQL не запущен, v5.local не найден).

---

## Следующий шаг оркестратора

1. M0 — проверить php/composer/node/v5/mysql
2. M1 — `bootstrap-from-v5.ps1`
3. M2 — `.env` + `provision-database.ps1` (БД **создаётся скриптом**)
4. M3 — migrate + `SaasDemoSeeder` (создать если нет)
5. M4 — smoke: 2 контрагента, лид → заказ

---

## Ключевые пути

| Что | Где |
| --- | --- |
| Оркестратор | `.cursor/agents/saas-migration-orchestrator.md` |
| State | `docs/sync/migration-state.json` |
| Runbook | `docs/sync/migration-runbook.md` |
| Код | `C:\OSPanel\home\saas\saas.local` |
| Git | https://github.com/tr0mb0zit76-bot/saas.git |

---

## Blockers

_(пусто — оркестратор заполнит при эскалации)_
