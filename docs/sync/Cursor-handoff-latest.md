# Cursor handoff — SaaS CRM (экспедиторы)

> **Оркестратор:** `saas-migration-orchestrator` · **Состояние:** `docs/sync/migration-state.json`

**Обновлено:** 2026-07-11 · **Ветка:** `cursor/migration-orchestrator-4010` · **Фаза:** **M4** (lab smoke, почти готово)

---

## Итог сессии — переезд продолжен (cloud lab)

| Блок | Статус |
| --- | --- |
| M0 Prerequisites | ✅ php, composer, node, v5 clone, MariaDB |
| M1 Bootstrap v5 → saas | ✅ код скопирован из github v5 |
| M2 Environment | ✅ .env, composer, npm |
| M3 Schema + seed | ✅ migrate + **SaasDemoSeeder** |
| M4 Smoke | 🟡 login 200, 4 contractors, 2 leads |
| M5 Tenancy | ⏳ следующий этап |

### Demo login (lab)

| Поле | Значение |
| --- | --- |
| URL | `http://saas.local` (Windows) / `http://127.0.0.1:8000` (cloud) |
| Admin | `admin@saas.local` / `password` |
| Manager | `manager@saas.local` / `password` |

### Demo data

- **Own company:** ООО «Демо Экспедиция»
- **Контрагенты:** ООО «Тест Заказчик», ИП «Тест Перевозчик» (+ own fleet из v5 seed)
- **Лиды:** 2 шт. (Москва → СПб)

---

## На Windows (OSPanel) — повторить lab

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull
pwsh -File scripts/setup-lab.ps1
# или если v5 локально:
pwsh -File scripts/bootstrap-from-v5.ps1
pwsh -File scripts/provision-database.ps1
composer install && npm ci
php artisan migrate --schema-path=database/schema/.skip-mysql-cli-load
php artisan db:seed --class=SaasDemoSeeder
npm run build
```

---

## Следующий шаг оркестратора

1. M4.4 — smoke: конвертация лида в заказ (UI или feature test)
2. M4.5 — `php artisan test --compact` smoke suite
3. **M5** — `tenants` + `tenant_id` + isolation tests

---

## Blockers

_(нет)_
