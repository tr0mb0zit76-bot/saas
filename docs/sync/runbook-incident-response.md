# Runbook — incident response

## Severity

| Level | Пример | SLA реакции |
|-------|--------|-------------|
| P1 | Все tenants down, data leak | немедленно |
| P2 | Один tenant, billing broken | < 4 ч |
| P3 | UI bug, non-critical | следующий спринт |

## P1 — total outage

1. Проверить OSPanel / nginx / PHP-FPM / MySQL
2. `php artisan down` только если нужен maintenance page
3. Логи: `storage/logs/laravel.log`, nginx error log
4. Откат: `git checkout {last-good}` + `composer install` + `php artisan migrate --force`
5. Restore DB из последнего backup (см. `runbook-backup-restore.md`)

## Tenant isolation breach (suspected)

1. `TenantContext::bypass(true)` — **не** использовать в prod без записи в audit
2. Проверить `/audit` — подозрительные platform actions
3. Suspend tenant в Platform → read-only mode
4. `php artisan test tests/Feature/Saas/TenantIsolationTest.php` (если есть)

## Suspend abusive tenant

Platform → Tenants → status **Приостановлен**. CRM read-only, banner для пользователей.

## Contacts

- Platform admin emails: `SAAS_PLATFORM_ADMIN_EMAILS` в `.env`
- On-call: (заполнить перед prod)
