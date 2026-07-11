# ADR-003: Упаковка модулей по тарифам

**Статус:** Accepted  
**Дата:** 2026-07-11

## Context

CRM v5 содержит ~20 модулей. Не все нужны внешним экспедиторам day-1. Нужна модульная упаковка для монетизации и упрощения onboarding.

## Decision

**Три тарифа с feature flags:**

| Tier | Модули | Лимиты |
| --- | --- | --- |
| **Start** | leads, orders, contractors, tasks, RBAC | 5 users, 100 orders/mo, 1 GB |
| **Pro** | Start + documents, payment_schedules, print, mail, sales_scripts, MCP read | 25 users, 500 orders/mo, 10 GB |
| **Enterprise** | Pro + management_accounting, load_board, integrations, custom domain, Traklo | custom |

### Реализация

```php
// tenant.settings.features: ['leads', 'orders', ...]
// Middleware: EnsureFeatureEnabled
// UI: скрывать menu items по features (как visibility_areas)
```

### Принцип

Не 100% parity с AA CRM. Упрощённые правила документов, default playbooks, per-tenant content.

## Consequences

### Positive
- Низкий порог входа (Start)
- Upsell path (Start → Pro)
- Меньше support burden на Start

### Negative
- Два кодовых пути (feature on/off)
- Клиенты могут хотеть модуль из Enterprise в Start

### Mitigations
- Add-on pricing для отдельных модулей
- 14-day trial на Pro features

## Out of MVP (6 months)

- White-label fork
- Custom domain for all
- Full AA document regulation
- OSINT security module

## Alternatives considered

1. **Flat pricing, all features** — отклонено: высокий порог, сложный onboarding
2. **Per-module à la carte only** — отклонено: слишком сложно для малого бизнеса
3. **Usage-only pricing** — отклонено: непредсказуемость для клиента
