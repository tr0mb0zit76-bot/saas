# Фаза 4 — безопасность и операционка

> Параллельно с Phases 1–3 · До первого paying customer

## Чеклист

- [ ] Audit log (orders, money, roles, tenant changes)
- [ ] Backup daily + restore drill (quarterly)
- [ ] CI/CD: pint, phpunit, npm build on push
- [ ] Staging environment
- [ ] Миграции через queue (не ручной SSH)
- [ ] Rate limits: signup, API, login
- [ ] 2FA для tenant-admin
- [ ] CSP headers
- [ ] Secrets в env, не в git
- [ ] Runbook: incident response
- [ ] Runbook: tenant onboarding ≤ 30 мин
- [ ] Monitoring: uptime, error rate, queue depth

## Audit log

```php
// tenant_audit_logs
tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip, created_at
```

События: order status change, payment recorded, role modified, user invited, document signed.

## CI pipeline

```yaml
# .github/workflows/ci.yml
- composer install
- php artisan test
- npm ci && npm run build
- laravel/pint --test
```

## Pentest scope (перед launch)

- Tenant isolation (A ≠ B)
- IDOR на orders/leads/documents
- MCP token scope
- File upload path traversal
- Subdomain takeover
