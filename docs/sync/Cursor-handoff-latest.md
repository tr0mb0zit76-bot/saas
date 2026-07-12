# Cursor handoff — Traklo Pro SaaS

**Обновлено:** 2026-07-12 · **Фаза:** Phase 4 · **Ветка:** `cursor/traklo-session-integrate-9d42` → PR в `main`

---

## ОТДАТЬ (эта сессия)

Склеены ветки сессии в **`cursor/traklo-session-integrate-9d42`**:

| Влито | Содержание |
| --- | --- |
| `restore-traklo-login-bubble` | Bubble-логин (поля в пузыре, «Почта») |
| `traklo-workspace-skin` | Опция внешнего вида **Traklo** (палитра витрины) |
| `traklo-showcase-feature-shots` | Лендинг: главы, horizontal rail, dissolve у TOC, матрица тарифов |
| `fix-lab-503` | Lab scripts + relative `/login` на одном хосте |

Не влито (уже в main или устарело / конфликты без выгоды): `platform-portal`, `subscription-plans`, `traklo-landing-login-animation`, `fix-platform-login-419`, `fix-embedded-browser`.

---

## Env (lab / home-pc)

```powershell
git pull origin cursor/traklo-session-integrate-9d42
# или после merge в main:
git pull origin main

pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
composer install --no-interaction
php artisan migrate --force
npm run build
```

```env
SHOWCASE_MODE=traklo_pro
SAAS_DEMO_SIGNUP_ENABLED=true
SAAS_DEFAULT_TENANT_SLUG=demo
PLATFORM_DOMAIN=platform.saas.local
SESSION_SECURE_COOKIE=false
TENANT_STORAGE_DISK=tenant_local
```

---

## UX (готово в integrate)

1. **Витрина** `/` — главы База/Про/Корпоративный, sticky horizontal rail, мягкое растворение у линии бренда, мгновенный jump по оглавлению.
2. **Логин** — bubble Traklo, не карточка под иконкой.
3. **CRM → Внешний вид → Traklo** — `workspace_skin=traklo` (navy + синий). Лучше с тёмной темой.

---

## M9–P4 (без изменений статуса)

- M9–M12, P4.1 core — **done** (см. предыдущий handoff)
- **Pending:** M9.5 browser smoke (`docs/sync/browser-smoke-howto.md`)
- Phase 4 backlog: 2FA, staging, monitoring

---

## На home-pc после pull

```powershell
git pull origin main
pwsh -File scripts/apply-saas-lab-env.ps1 -HostName saas.local
composer install --no-interaction
php artisan migrate --force
npm run build
```

При 503 в браузере: `pwsh -File scripts/fix-lab-proxy-bypass.ps1` / `scripts/diagnose-lab-http.ps1`

**Demo:** `/` → Демо-доступ → email → login → onboarding → CRM  
**Checklist:** `docs/sync/pilot-smoke-checklist.md`

---

## Следующие шаги

1. Merge PR integrate → `main`
2. Browser smoke на home-pc
3. 2FA / staging / monitoring (P4 remainder)
