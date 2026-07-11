# Фаза 2 — онбординг и биллинг

> Срок: 1–2 месяца

## Цель

Self-service signup → trial → оплата → лимиты.

## Signup flow

```
Landing → Register (email, password, company name)
  → Create Tenant (slug auto from company name)
  → Onboarding wizard (timezone, currency, own company name)
  → Seed default roles + business process
  → Trial 14 days → Dashboard
```

## Биллинг

| Параметр | Решение |
| --- | --- |
| Провайдер | ЮKassa или CloudPayments (не свой биллинг) |
| Trial | 14 дней, auto-provision |
| Лимиты | users, orders/month, storage GB |
| Suspend | read-only при неоплате |
| Invoices | email + PDF |

## Таблицы

- `tenant_subscriptions` — plan, status, external_id (payment provider)
- `tenant_usage_logs` — daily snapshot: users, orders, storage

## Email

- Welcome
- Trial ending (3 days, 1 day)
- Payment failed
- Invoice

## Gate → Phase 3

- Signup работает end-to-end
- Оплата Pro tier на staging
- Suspend/unsuspend tested
