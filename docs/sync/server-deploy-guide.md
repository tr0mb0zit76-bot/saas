# Инструкция: разворачивание Traklo Pro на боевом тестовом сервере

> Канон топологии: `docs/architecture/decisions/ADR-014-staging-topology.md`  
> Чеклист: `docs/sync/staging-checklist.md`  
> Env-шаблон: `.env.staging.example`  
> Скрипт: `scripts/deploy-staging.sh`

## 0. Что нужно заранее

| Ресурс | Пример |
| --- | --- |
| Linux VM | Ubuntu 22.04+, 2+ vCPU, 4+ GB RAM |
| DNS | `crm.example.com`, `*.crm.example.com`, `platform.crm.example.com` |
| TLS | wildcard cert (Let's Encrypt DNS-01 или провайдер) |
| Секреты | `APP_KEY`, MySQL, Redis, S3, SMTP, Gotenberg URL |

## 1. ПО на сервере

```bash
# PHP 8.3 + расширения (fpm, mysql, redis, xml, mbstring, zip, gd, intl, bcmath)
# nginx, MySQL 8, Redis, Composer, Node 20+, Supervisor
# Docker (опционально) для Gotenberg:
docker run -d --name gotenberg -p 127.0.0.1:3000:3000 gotenberg/gotenberg:8
```

## 2. Код и окружение

```bash
sudo mkdir -p /var/www/traklo && sudo chown $USER:$USER /var/www/traklo
cd /var/www/traklo
git clone <YOUR_REPO_URL> .
git checkout main   # или release-tag

cp .env.staging.example .env
# заполнить секреты: APP_KEY (php artisan key:generate), DB_*, REDIS_*, AWS_*, MAIL_*,
# CRM_DOMAIN, PLATFORM_DOMAIN, APP_URL=https://CRM_DOMAIN
# Обязательно:
#   APP_ENV=production
#   APP_DEBUG=false
#   SESSION_SECURE_COOKIE=true
#   SAAS_LEGACY_AA_INTEGRATIONS=false
#   SAAS_DEMO_SIGNUP_ENABLED=false   # если пилоты по invite
#   TENANT_STORAGE_DISK=tenant_s3

composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate   # если APP_KEY пустой
php artisan migrate --force
php artisan db:seed --class=SaasDemoSeeder --force   # только если нужны demo-тенанты
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Повторный деплой:

```bash
bash scripts/deploy-staging.sh main
```

## 3. nginx (схема)

- `server_name crm.example.com *.crm.example.com;` → `public/`
- Отдельный server для `platform.crm.example.com` → тот же `public/`
- `client_max_body_size` ≥ лимита загрузки документов
- HTTPS only; `X-Forwarded-Proto` от TLS terminator

## 4. Supervisor + cron

**queue** (Redis):

```
php /var/www/traklo/artisan queue:work redis --sleep=1 --tries=3
```

**cron** (от пользователя деплоя):

```
* * * * * cd /var/www/traklo && php artisan schedule:run >> /dev/null 2>&1
```

Ночной бэкап: `php artisan saas:backup-database` (см. `runbook-backup-restore.md`).

## 5. Проверка после деплоя

1. `https://crm.example.com/up` → 200  
2. Витрина `/` и логин  
3. `https://demo.crm.example.com/login` (tenant subdomain)  
4. `https://platform.crm.example.com/login` — platform admin  
5. Загрузка файла тенанта → объект в S3  
6. Smoke: `docs/sync/browser-smoke-howto.md` §7  

## 6. Чего НЕ включать на staging

- `SAAS_LEGACY_AA_INTEGRATIONS=true` (АА 1С/Astral stubs)  
- `APP_DEBUG=true`  
- Seed боевых данных АА  

## 7. Откат

```bash
git fetch && git checkout <previous-tag>
bash scripts/deploy-staging.sh <previous-tag>
# при необходимости: restore DB из backup runbook
```
