# Runbook — tenant onboarding ≤ 30 мин

## Demo self-service (trial)

1. Пользователь: `/` → Демо-доступ → email
2. Авто: tenant trial Start, admin user, welcome mail
3. Login → `/onboarding` → CRM

**Env:** `SAAS_DEMO_SIGNUP_ENABLED=true`

## Platform manual (paying / pilot)

1. Platform login → `/tenants` → «Новый арендатор»
2. Slug, название, тариф, trial dates
3. Admin name + email, «Отправить приглашение»
4. Проверить: 7 roles, subscription row, welcome mail
5. Клиент: login → onboarding → smoke (`browser-smoke-howto.md`)

## Post-create checklist

- [ ] Tenant в `/audit` → `tenant.created`
- [ ] Лимиты тарифа OK (`/plans/{key}/features`)
- [ ] Feature overrides при необходимости (`/tenants/{id}/features`)
- [ ] Invoice PDF (после первого billing period)

## Rollback

Suspend tenant. Удаление — только вручную в БД (нет UI delete в MVP).
