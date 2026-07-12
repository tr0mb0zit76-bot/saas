# Runbook — backup & restore

## Daily backup (lab / staging)

```powershell
php artisan saas:backup-database
```

Файл: `storage/app/backups/saas-{db}-{timestamp}.sql.gz`

Требуется `mysqldump` в PATH (OSPanel: `modules\MySQL-8.x\bin`).

### Cron (Linux prod)

```cron
15 2 * * * cd /var/www/saas && php artisan saas:backup-database >> /var/log/saas-backup.log 2>&1
```

## Restore drill (quarterly)

1. Создать тестовую БД `saas_crm_restore_test`
2. `gunzip -c storage/app/backups/saas-*.sql.gz | mysql -u root -p saas_crm_restore_test`
3. В `.env.testing` указать restore DB, прогнать `php artisan test tests/Feature/Saas/PilotSmokeTest.php`
4. Удалить тестовую БД

## Tenant files

Файлы арендаторов: `storage/tenants/{id}/` (или S3 prefix). Бэкап БД **не** включает файлы — копировать отдельно.

## Export per tenant (churn / 152-ФЗ prep)

```powershell
php artisan saas:export-tenant {slug}
```

ZIP manifest в `storage/app/exports/`.
