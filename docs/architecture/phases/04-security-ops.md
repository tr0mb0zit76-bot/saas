# Фаза 4 — безопасность и операционка

> Параллельно с Phases 1–3 · До первого paying customer

## Чеклист

- [x] Audit log (orders, money, roles, tenant changes, documents, invites)
- [x] Backup command `saas:backup-database` + runbook restore drill
- [x] CI/CD: `.github/workflows/ci.yml` — pint, SaaS tests, npm build
- [ ] Staging environment (отдельный host — manual)
- [ ] Миграции через queue (не ручной SSH)
- [x] Rate limits: signup (3/60), login (5), API (`throttle:api` 180/min)
- [ ] 2FA для tenant-admin
- [x] Security headers + optional CSP (`SECURITY_HEADERS_CSP_ENABLED`)
- [x] Secrets в env, не в git (`.env` gitignored, scripts for lab)
- [x] Runbook: incident response (`docs/sync/runbook-incident-response.md`)
- [x] Runbook: tenant onboarding (`docs/sync/runbook-tenant-onboarding.md`)
- [ ] Monitoring: uptime, error rate, queue depth

## Audit log

```php
// tenant_audit_logs
tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip, created_at
```

События (implemented): order status, payment recorded/reversed, role CRUD, user created/roles, document signed, user invited, platform tenant/plan changes.

## CI pipeline

```yaml
# .github/workflows/ci.yml
- composer install
- php artisan test tests/Feature/Saas/
- npm ci && npm run build
- laravel/pint --test
```

## Browser smoke (manual)

`docs/sync/browser-smoke-howto.md` · automated: `PilotSmokeTest`

## Pentest scope (перед launch)

- Tenant isolation (A ≠ B)
- IDOR на orders/leads/documents
- MCP token scope
- File upload path traversal
- Subdomain takeover
