# Pilot smoke checklist — первый внешний экспедитор

**Цель:** чистый demo-tenant без prod-данных Avtoalyans. Проверка end-to-end за ~30 минут на home-pc lab.

## Подготовка (home-pc)

```powershell
cd C:\OSPanel\home\saas\saas.local
git pull origin main
composer install --no-interaction
php artisan migrate --force
npm run build
```

`.env` (минимум):

```env
SHOWCASE_MODE=traklo_pro
TENANT_STORAGE_DISK=tenant_local
SAAS_PLATFORM_ADMIN_EMAILS=admin@saas.local
SAAS_DEMO_SIGNUP_ENABLED=true
SESSION_SECURE_COOKIE=false
DOC_PREVIEW_DRIVER=gotenberg
GOTENBERG_URL=http://127.0.0.1:3000
```

## 1. Витрина и demo signup

- [ ] `/` → Traklo Pro landing
- [ ] CTA «Демо-доступ» → `/demo/signup` (`SAAS_DEMO_SIGNUP_ENABLED=true`)
- [ ] Форма demo → email с паролем → login → `/onboarding` → dashboard
- [ ] `/login` → TrakloLoginScene

## 2. Platform (если не через demo)

- [ ] `/platform/tenants` → «Новый арендатор»
- [ ] Slug, название, тариф Start, trial
- [ ] ФИО и email администратора, галочка «Отправить приглашение»
- [ ] После создания: 7 ролей, 1 admin user, письмо с временным паролем (если SMTP настроен)

## 3. Первый вход арендатора

- [ ] Вход по email из приглашения (subdomain или `saas.local` + slug)
- [ ] Смена пароля в профиле
- [ ] Меню CRM: Лиды, Заказы, Контрагенты доступны (Start)

## 4. Лимиты тарифа Start

- [ ] Настройки → Пользователи: лимит 5 (6-й — ошибка)
- [ ] Создание заказа: лимит 200/мес (201-й — ошибка)

## 5. Биллинг

- [ ] Platform → «Оплачено» для tenant
- [ ] Кнопка **PDF** — счёт открывается (Gotenberg)
- [ ] Статус tenant → active, billing_period_end продлён

## 6. Модули (опционально Pro)

- [ ] Platform → Модули арендатора: override mail/documents
- [ ] Проверка feature gating на Start vs Pro

## Rollback

- Suspend tenant в Platform
- Удаление тестового tenant — только вручную в БД (нет UI delete в MVP)
