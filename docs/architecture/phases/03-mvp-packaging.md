# Фаза 3 — MVP для арендаторов

> Срок: 2–3 месяца

## Цель

Модульная упаковка по тарифам, demo tenant, pilot onboarding.

## Feature flags

```php
// tenant.settings или tenant.features JSON
['leads', 'orders', 'contractors', 'tasks',           // Start
 'documents', 'payment_schedules', 'print', 'mail',   // Pro
 'management_accounting', 'load_board']                // Enterprise
```

Middleware `EnsureFeatureEnabled:documents` на routes.

## Упрощения vs v5 (AA)

| Модуль | AA (full) | SaaS MVP |
| --- | --- | --- |
| Documents | Полный регламент v1.0 | Упрощённый чек-лист |
| Business process | Кастомные playbooks | Default seed + edit |
| Sales Book | Sync из git | Per-tenant content |
| Print templates | Per own_company | Per tenant, 2–3 defaults |
| KPI | Полная мотивация | Базовые метрики |

## Demo tenant

```bash
php artisan tenant:seed-demo --slug=demo
```

Содержит: 5 users, 10 leads, 5 orders, 3 contractors.

## Pilot onboarding

Runbook: onboarding ≤ 30 мин
1. Create tenant (or self-signup)
2. Import contractors (CSV)
3. Configure own company
4. Invite users
5. First lead → order

## Gate → Launch

- 2–3 pilots на Pro tier
- In-app help links
- Support channel (email/Telegram)
