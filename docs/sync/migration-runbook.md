# Migration runbook — v5 → SaaS (оркестратор)

> **Исполнитель:** субагент `saas-migration-orchestrator`  
> **Состояние:** `docs/sync/migration-state.json`  
> **Цель:** lab на `http://saas.local` без участия человека (кроме blockers)

---

## Принципы

1. **Копируем v5, не переписываем** — bootstrap + точечные правки
2. **Отдельная БД** `saas_crm` — создаётся скриптом
3. **State file** — единственный источник «где остановились»
4. **Человек** — только если MySQL/v5/path недоступны после auto-retry

---

## M0 — Prerequisites

| Step | Action | Verify | Auto |
| --- | --- | --- | --- |
| M0.1 | `git pull`, clean working tree | `git status` | ✅ |
| M0.2 | PHP 8.3+ in PATH | `php -v` | ✅ |
| M0.3 | Composer in PATH | `composer -V` | ✅ |
| M0.4 | Node 18+ in PATH | `node -v` | ✅ |
| M0.5 | v5.local exists | `Test-Path v5Root` | ✅ discover `-V5Root` |
| M0.6 | MySQL reachable | `provision-database.ps1 -WhatIf` | ✅ |

**Gate:** all M0 ✅ → M1

---

## M1 — Bootstrap

| Step | Action | Verify |
| --- | --- | --- |
| M1.1 | `bootstrap-from-v5.ps1 -WhatIf` | preview OK |
| M1.2 | `bootstrap-from-v5.ps1` | `artisan` exists |
| M1.3 | Preserve saas docs/scripts/.cursor | diff check |

**Gate:** Laravel skeleton present → M2

---

## M2 — Environment

| Step | Action | Verify |
| --- | --- | --- |
| M2.1 | Copy `.env.example` → `.env` if missing | file exists |
| M2.2 | Set `APP_URL=http://saas.local`, `DB_DATABASE=saas_crm` | grep .env |
| M2.3 | `provision-database.ps1` | DB exists |
| M2.4 | `composer install --no-interaction` | vendor/ |
| M2.5 | `npm ci` | node_modules/ |
| M2.6 | `php artisan key:generate` | APP_KEY set |

**Gate:** `php artisan about` runs → M3

---

## M3 — Schema & seed

| Step | Action | Verify |
| --- | --- | --- |
| M3.1 | `php artisan migrate --force` | migrations OK |
| M3.2 | Base seeders if v5 has them | roles/users |
| M3.3 | `SaasDemoSeeder` (create if missing) | 2 contractors, own company, admin |
| M3.4 | `npm run build` | public/build |

**Gate:** login + contractors count → M4

---

## M4 — Lab smoke

| Step | Action | Verify |
| --- | --- | --- |
| M4.1 | HTTP `/login` 200 | curl/browser |
| M4.2 | Contractors ≥ 2 | tinker/test |
| M4.3 | Create or seed 1 lead | Lead exists |
| M4.4 | Convert lead → order (test or manual script) | Order exists |
| M4.5 | `php artisan test --compact` | pass or document skip |

**Gate:** smoke green → M5

---

## M5 — Tenancy

| Step | Action | Verify |
| --- | --- | --- |
| M5.1 | `tenants` table + model | migration |
| M5.2 | `tenant_id` Tier A tables | migration |
| M5.3 | `TenantScope` + middleware | unit test |
| M5.4 | Isolation test A≠B | feature test green |
| M5.5 | Seed demo-a, demo-b tenants | 2 subdomains doc |

**Gate:** isolation tests green → M6

---

## M6 — Audit hardening

See `docs/architecture/saas-audit-remediation.md`:
- P0.10 scope/IDOR fixes
- P1.1 department scope backend

**Gate:** checklist complete → M7

---

## M7 — Ready for pilots

| Step | Action |
| --- | --- |
| M7.1 | Feature flags skeleton |
| M7.2 | Update architecture-plan + handoff |
| M7.3 | sync-docs-to-yandex.ps1 |
| M7.4 | README «how to run lab» |

---

## One-command setup

```powershell
pwsh -File scripts/setup-lab.ps1
pwsh -File scripts/migration-status.ps1
```

## Status check

```powershell
pwsh -File scripts/migration-status.ps1
```

---

## Human escalation template

Orchestrator writes to handoff only:

```
BLOCKER: [M2.3] MySQL unreachable
TRIED: 127.0.1.21, 127.0.0.1, localhost; root empty + .env
NEED: confirm OSPanel MySQL running OR provide DB_HOST/DB_PASSWORD
```

*Обновлено: 2026-07-11*
