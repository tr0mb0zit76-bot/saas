# ADR-002: Изоляция tenant

**Статус:** Accepted  
**Дата:** 2026-07-11

## Context

SaaS обслуживает множество экспедиторов. Утечка данных между tenants — критический инцидент. Нужно выбрать модель изоляции.

## Decision

**Single database + `tenant_id` column** на всех бизнес-таблицах (Tier A/B).

### Компоненты

1. **`tenants` table** — slug, name, status, plan, settings
2. **`tenant_id` FK** — на ~80 таблицах
3. **`IdentifyTenant` middleware** — subdomain → tenant context
4. **`TenantScope` global scope** — автоматический фильтр на всех queries
5. **`TenantContext`** — thread-local tenant для jobs/commands
6. **Storage:** `storage/tenants/{id}/`
7. **Tests:** tenant A never sees tenant B (обязательный CI gate)

### Идентификация

- MVP: subdomain `{slug}.crm.ru`
- Enterprise: custom domain (CNAME)

## Consequences

### Positive
- Простые миграции и бэкапы (одна БД)
- До 50–200 tenants без усложнения
- Tenant #1 backfill тривиален

### Negative
- Один SQL injection без scope = утечка всех tenants
- Noisy neighbor на shared DB
- Сложнее enterprise compliance (данные в одной БД)

### Mitigations
- Global scopes + code review + isolation tests
- Rate limits per tenant
- Schema-per-tenant как upgrade path для Enterprise

## Alternatives considered

1. **Schema per tenant** — отклонено для MVP: сложные миграции на N schemas
2. **DB per tenant** — отклонено: ops overhead
3. **`stancl/tenancy` as-is** — частично: домен настолько свой, что большая часть кастом
