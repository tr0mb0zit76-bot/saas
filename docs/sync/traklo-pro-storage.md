# Traklo Pro — хранилище файлов и масштаб

> **Канон в git:** `docs/sync/traklo-pro-storage.md`  
> **Vault:** `Exchange/saas/traklo-pro-storage.md`  
> **Синхронизация:** `pwsh -File scripts/sync-docs-to-yandex.ps1`  
> **ADR:** [[architecture/decisions/ADR-005-tenant-file-storage|ADR-005]], [[architecture/decisions/ADR-011-ai-llm-multi-tenant-data|ADR-011]], [[architecture/decisions/ADR-012-database-scaling-strategy|ADR-012]]

---

## Короткий ответ

| Окружение | Нужен ли S3? | Что использовать |
|-----------|--------------|------------------|
| **Локальный SaaS lab** (OSPanel, home-pc) | **Нет** | `TENANT_STORAGE_DISK=tenant_local` → папка `storage/app/tenants/` |
| **Prod Traklo Pro** | **Да** | Yandex Object Storage (S3 API) → `tenant_s3` |
| **Dev «как prod»** (опционально) | По желанию | MinIO в Docker — только если тестируете S3 API |

На home-pc **не обязательно** поднимать S3. Layout путей **тот же**, что в prod — переключение одной переменной в `.env`.

---

## Как устроены пути (lab = prod)

```
tenants/{tenant_id}/order_documents/{order_id}/file.pdf
tenants/{tenant_id}/mail_inbound/…
```

Код: `App\Support\TenantStorage` — всегда добавляет префикс `tenants/{id}/`.

**Lab (диск):**

```
C:\OSPanel\home\saas\saas.local\storage\app\tenants\
├── 1\order_documents\…
├── 2\…
```

**Prod (S3):**

```
s3://your-bucket/tenants/1/order_documents/…
```

---

## Локальный SaaS — `.env`

```env
TENANT_STORAGE_DISK=tenant_local
```

Больше ничего для файлов не нужно. Nextcloud для Traklo Pro **не используем** (legacy v5 / одна компания AA).

Проверка после upload:

```powershell
dir C:\OSPanel\home\saas\saas.local\storage\app\tenants\
```

---

## Prod — Yandex Object Storage

1. Создать bucket в Yandex Cloud (например `traklo-pro-prod`).
2. Сервисный аккаунт + статический ключ (S3-compatible).
3. В `.env` на сервере:

```env
TENANT_STORAGE_DISK=tenant_s3
TENANT_STORAGE_ROOT_PREFIX=traklo-pro-prod
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_DEFAULT_REGION=ru-central1
AWS_BUCKET=traklo-pro-prod
AWS_ENDPOINT=https://storage.yandexcloud.net
AWS_USE_PATH_STYLE_ENDPOINT=true
```

4. `composer require league/flysystem-aws-s3-v3` (если ещё нет в проекте).

Lifecycle: правила на prefix `tenants/` — архивация старых документов.

---

## Опционально: MinIO локально (эмуляция S3)

Только если разработчику нужен **тот же API**, что в prod (presigned URLs, lifecycle, multipart).

```yaml
# docker-compose.minio.yml (черновик)
services:
  minio:
    image: minio/minio
    ports:
      - "9000:9000"
    environment:
      MINIO_ROOT_USER: minio
      MINIO_ROOT_PASSWORD: minio123456
    command: server /data
```

```env
TENANT_STORAGE_DISK=tenant_s3
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_BUCKET=traklo-local
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Для обычной работы на OSPanel **MinIO не нужен**.

---

## Nextcloud vs S3

| | Nextcloud (v5) | S3 (Traklo Pro) |
|---|----------------|-----------------|
| Масштаб | одна компания | сотни tenants |
| Ops | ручной NC, WebDAV | bucket + IAM, automation |
| SaaS | не развиваем | **целевой путь** |
| Миграция | export → `TenantStorage` upload | — |

---

## База данных и AI (связанное)

- **MySQL одна** на старте, `tenant_id` на строках — см. [[architecture/decisions/ADR-012-database-scaling-strategy|ADR-012]].
- **LLM один pool**, история агентов с `tenant_id` — см. [[architecture/decisions/ADR-011-ai-llm-multi-tenant-data|ADR-011]].
- Файлы **не в MySQL** — только метаданные + S3 key.

---

## Subdomain и storage

Каждый клиент: `{slug}.crm.ru` → tenant в БД → файлы в `tenants/{id}/` на S3.  
Subdomain не создаёт отдельный bucket — только логический tenant id.

---

## Чеклист home-pc

- [ ] `.env`: `TENANT_STORAGE_DISK=tenant_local`
- [ ] Не настраивать Nextcloud для saas.local
- [ ] S3 / MinIO — не требуется для lab
- [ ] После `git pull` — `pwsh -File scripts/sync-docs-to-yandex.ps1` (этот файл в Obsidian)

---

*Обновлено: 2026-07-11 · Traklo Pro*
