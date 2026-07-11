# Roles and permissions

## Platform roles

| Role | Purpose |
| --- | --- |
| `platform_owner` | Full SaaS ownership and critical settings |
| `platform_admin` | Tenant, plan, billing, and support operations |
| `platform_support` | Limited diagnostics with audit trail |

## Tenant roles

| Role | Purpose |
| --- | --- |
| `tenant_owner` | Full control over a tenant company |
| `tenant_admin` | Settings, users, roles, dictionaries |
| `dispatcher` | Orders, statuses, carrier assignment |
| `sales_manager` | Customers, quotes, commercial work |
| `logistics_manager` | Carriers, routes, rates |
| `accountant` | Invoices, acts, payments, debt |
| `document_manager` | Templates, generated documents, document status |
| `viewer` | Read-only access |
| `customer_portal_user` | External customer access |
| `carrier_portal_user` | External carrier access |

## Permission examples

```text
tenants.read
tenants.manage
users.read
users.invite
users.manage
customers.read
customers.manage
carriers.read
carriers.manage
orders.read
orders.create
orders.update
orders.cancel
documents.read
documents.manage
finance.read
finance.manage
reports.read
settings.manage
```

The final permission matrix must be validated against the existing CRM roles.
