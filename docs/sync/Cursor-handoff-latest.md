# Cursor handoff — SaaS CRM

**Обновлено:** 2026-07-11 · **Фаза:** **M5 done** → M6 audit hardening next

---

## Автономный переезд — выполнено

| Фаза | Статус |
|------|--------|
| M0–M3 Bootstrap + DB + seed | ✅ |
| M4 Lab smoke | ✅ lead→order, tests green |
| M5 Tenancy | ✅ tenant_id, isolation, demo-a/b |
| M6 Audit | ⏳ автоматически дальше |

### Demo login

| Tenant | URL | User |
|--------|-----|------|
| **demo** | `http://saas.local` | `admin@saas.local` / `password` |
| **demo-a** | header `X-Tenant-Slug: demo-a` | `manager@demo-a.saas.local` / `password` |
| **demo-b** | header `X-Tenant-Slug: demo-b` | `manager@demo-b.saas.local` / `password` |

### Автокоманды (Windows)

```powershell
pwsh -File scripts/setup-lab.ps1   # всё: bootstrap, DB, migrate, seed, smoke
php artisan saas:smoke-lab         # повторный smoke
php artisan test tests/Feature/Saas/
```

### Данные lab

- 4+ contractors, 2 leads, 1+ orders (конвертация лида)
- Tenants: demo, demo-a, demo-b

---

## Следующий автоматический шаг (M6)

- P0.10 / P1.1 из audit remediation
- Feature flags skeleton (M7)

**Human needed:** нет
