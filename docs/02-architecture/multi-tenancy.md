# Multi-tenancy strategy

## Decision for MVP

Start with:

- shared application;
- shared database;
- mandatory `tenant_id` on tenant-owned records;
- centralized RBAC and permission checks;
- strict query scoping by tenant context.

## Tenant context

Every authenticated request must resolve:

```text
user_id
tenant_id
roles
permissions
subscription status
feature flags
```

Business services should require tenant context explicitly. Data access code
must not be able to query tenant-owned tables without tenant scoping.

## Tenant-owned tables

Every tenant-owned table should include:

```text
id
tenant_id
created_at
updated_at
deleted_at
created_by
updated_by
```

Examples:

```text
customers
contacts
orders
shipments
carriers
vehicles
drivers
documents
invoices
payments
settings
```

## Unique constraints

Tenant-owned uniqueness must include `tenant_id`.

Examples:

```text
(tenant_id, email)
(tenant_id, customer_code)
(tenant_id, order_number)
(tenant_id, document_number)
```

## Platform-owned tables

Platform data is not tenant-scoped in the same way:

```text
tenants
plans
subscriptions
billing_events
platform_users
platform_audit_events
```

Access to platform-owned data must be limited to platform roles.

## Roles

### Platform roles

- `platform_owner`
- `platform_admin`
- `platform_support`

### Tenant roles

- `tenant_owner`
- `tenant_admin`
- `dispatcher`
- `sales_manager`
- `logistics_manager`
- `accountant`
- `document_manager`
- `viewer`
- `customer_portal_user`
- `carrier_portal_user`

## Permission examples

```text
orders.read
orders.create
orders.update
orders.cancel
customers.manage
carriers.manage
finance.read
finance.manage
documents.read
documents.manage
settings.manage
users.invite
users.manage
reports.read
```

## Audit log

Audit events should include:

```text
tenant_id
actor_user_id
action
entity_type
entity_id
before
after
ip_address
user_agent
created_at
```

Platform support actions must always be logged.

## Future enterprise isolation

The MVP model should not block later isolation upgrades:

- separate database per tenant;
- separate storage bucket per tenant;
- tenant-specific encryption key;
- dedicated worker queue;
- custom backup retention;
- custom region.
