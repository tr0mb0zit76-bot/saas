# Cursor handoff — SaaS CRM

**Обновлено:** 2026-07-11 · **main** merged · **Фаза:** M5 done

---

## Локальный OSPanel (Windows)

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
pwsh -File scripts/setup-os-panel.ps1
```

Если репо случайно лежит в `...\saas.local\saas.local`, setup исправит путь сам.  
Или вручную: `pwsh -File scripts/fix-nested-repo-path.ps1`

Открыть: **http://saas.local**  
Login: **admin@saas.local** / **password**

Скрипт сам: bootstrap v5 → `.env` (127.0.1.21) → БД `saas_crm` → composer → npm → migrate → seed → smoke.

---

## Demo tenants

| Slug | User |
|------|------|
| demo | admin@saas.local |
| demo-a | manager@demo-a.saas.local |
| demo-b | manager@demo-b.saas.local |

Пароль: `password`

---

## Blockers

_(нет)_
