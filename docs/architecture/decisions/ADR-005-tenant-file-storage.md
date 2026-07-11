# ADR-005: Tenant file storage — S3-first

**Status:** Accepted (supersedes 2026-07-11 draft)  
**Date:** 2026-07-11

## Decision

**Traklo Pro uses S3-compatible object storage** as the target production backend for all tenant-owned files. Implementation via `App\Support\TenantStorage` and Laravel disk `tenant_s3`.

**Nextcloud WebDAV is legacy (v5 / single-tenant AA only)** — not extended for SaaS multi-tenant path.

### Object key layout (local lab = same paths)

```
tenants/{tenant_id}/order_documents/{order_id}/file.pdf
tenants/{tenant_id}/mail_inbound/{user_id}/{message_id}/attach.eml
tenants/{tenant_id}/print_forms/…
```

Optional bucket prefix: `TENANT_STORAGE_ROOT_PREFIX=traklo-pro-prod`

### Environments

| Env | Disk | Config |
| --- | --- | --- |
| Lab | `tenant_local` | `storage/app/tenants/` |
| Prod | `tenant_s3` | Yandex Object Storage / AWS / MinIO |

`TENANT_STORAGE_DISK=tenant_local|tenant_s3`

### Migration from Nextcloud (AA → SaaS)

One-time export per tenant → upload to `tenants/{id}/` via `TenantStorage`. No shared Nextcloud folder for SaaS customers.

## Consequences

- `DocumentStorageService` refactored to call `TenantStorage` (incremental PRs).
- Lifecycle rules on S3 prefix per tenant for retention / cost.
- Enterprise optional: dedicated bucket per tenant (contract), same API.

## Alternatives rejected

- **Nextcloud multi-tenant folders** — ops burden, weak automation at scale.
- **Per-tenant Nextcloud** — unacceptable ops cost.
