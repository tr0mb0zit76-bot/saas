# Фаза 1 — tenancy и изоляция

> Срок: 2–3 месяца · Технический фундамент

## Цель

`tenant_id` на всех бизнес-таблицах, middleware, global scopes, isolation tests. Tenant #1 = Автоальянс.

## Шаги

### 1. Bootstrap кода

```powershell
pwsh -File scripts/bootstrap-from-v5.ps1
composer install && npm ci
```

### 2. Модель Tenant

```php
// tenants table
id, slug, name, status (active|suspended|trial), plan (start|pro|enterprise),
settings (JSON), trial_ends_at, created_at
```

### 3. tenant_id миграции

Приоритет Tier A (см. architecture-plan §5):
- `users.tenant_id` (nullable для super-admin)
- `orders.tenant_id`, `leads.tenant_id`, `contractors.tenant_id`, …

### 4. Middleware IdentifyTenant

```
Request → resolve host → Tenant::where('slug', $subdomain) → TenantContext::set($tenant)
```

### 5. TenantScope

```php
// На каждой бизнес-модели
static::addGlobalScope(new TenantScope);
// booted(): creating → auto-set tenant_id
```

### 6. Storage

`storage/tenants/{tenant_id}/documents/...`

### 7. Queue

```php
// Job middleware
TenantContext::set($this->tenantId);
```

### 8. Backfill

```sql
UPDATE orders SET tenant_id = 1 WHERE tenant_id IS NULL;
-- ... все Tier A/B таблицы
```

### 9. Isolation tests

```php
// Feature test
$tenantA = Tenant::factory()->create();
$tenantB = Tenant::factory()->create();
// Create order in A, login as B user → 404
```

### 10. Super-admin

- `/admin/tenants` — список, suspend, usage
- Без tenant scope для super-admin routes

### 11. Close RBAC gaps

Перенести/исправить из v5 audit:
- `PaymentScheduleAutomaticStatus::refreshForOrdersScope`
- `LeadAttentionQueueService` department scope
- `FinanceOverviewService` scope

## Оценка

| Блок | Недели |
| --- | --- |
| Bootstrap + setup | 1 |
| Tenant model + middleware | 1–2 |
| Миграции tenant_id (Tier A) | 2–3 |
| Global scopes + tests | 2 |
| Storage + queue | 1 |
| Super-admin | 1–2 |
| RBAC gap fixes | 1–2 |

**Итого:** 8–14 недель

## Gate → Phase 2

- Isolation tests green
- Tenant #1 (AA) работает на staging
- Super-admin может создать tenant #2 вручную
