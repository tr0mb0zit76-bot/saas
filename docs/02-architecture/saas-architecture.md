# SaaS architecture blueprint

## Product objective

Build a multi-tenant CRM platform for forwarding and logistics companies. Each
customer company becomes a tenant with isolated users, customers, orders,
documents, finances, settings, and integrations.

```text
SaaS platform
├─ Platform administration
├─ Tenant A: forwarding company
├─ Tenant B: forwarding company
└─ Tenant C: forwarding company
```

## Architectural layers

### Platform layer

Owns capabilities that belong to the SaaS provider:

- tenant lifecycle;
- subscriptions, billing, limits, and usage;
- platform support access;
- platform observability;
- global feature flags;
- incident and maintenance operations.

### Tenant layer

Owns business functionality for each forwarding company:

- company profile and legal details;
- users, roles, teams, and invitations;
- customers and counterparties;
- transportation orders;
- carriers, vehicles, and drivers;
- documents and templates;
- financial operations;
- reports and analytics;
- tenant-specific integrations.

### Integration layer

Connects tenant data to external systems:

- email;
- telephony;
- messaging;
- maps and geocoding;
- electronic document interchange;
- accounting;
- banks;
- freight exchanges;
- public API for customers and carriers.

Integration choices must be confirmed after discovery of the current CRM.

## Recommended first multi-tenancy model

Use a shared application and a shared database with mandatory `tenant_id` on all
tenant-owned business records.

This model is suitable for an MVP because it keeps operations simpler while
still enforcing data isolation at the application, database query, and
permission layers.

Future enterprise isolation can add:

- dedicated database per tenant;
- dedicated file storage bucket;
- tenant-specific encryption keys;
- region-specific deployment;
- custom retention policies.

## Core SaaS modules

### Tenant management

- tenant registration;
- company details;
- legal details;
- time zone, currency, locale;
- plan, limits, and subscription status;
- tenant settings and custom dictionaries.

### Identity and access

- user registration and invitations;
- tenant membership;
- RBAC and permission matrix;
- session management;
- two-factor authentication;
- audit log;
- controlled support impersonation.

### Counterparties

- customers;
- shippers;
- consignees;
- carriers;
- contacts;
- legal details;
- contracts;
- interaction history.

### Transportation orders

Central operational entity for dispatchers and managers.

Typical statuses:

```text
draft
new
in_processing
carrier_assigned
in_transit
delivered
closed
cancelled
claim
```

Key fields:

- customer;
- route;
- loading and unloading points;
- cargo;
- weight, volume, package count;
- temperature mode;
- customer price;
- carrier rate;
- margin;
- responsible user;
- linked documents;
- status history.

### Carriers

- carrier profile;
- transport units;
- drivers;
- documents;
- rating;
- blacklist or whitelist;
- transport history;
- debt and payment status.

### Finance

- customer invoices;
- carrier invoices;
- acts;
- payments;
- accounts receivable and payable;
- margin;
- VAT modes;
- currencies;
- reconciliation.

### Documents

- document templates;
- contracts;
- invoices;
- acts;
- transportation documents;
- file storage;
- versions;
- signatures;
- future EDI integration.

### Analytics

Tenant analytics:

- orders by status;
- revenue;
- margin;
- debt;
- manager performance;
- common lanes;
- carrier reliability;
- document delays;
- delivery SLA.

Platform analytics:

- active tenants;
- MRR and ARR;
- churn;
- usage by plan;
- infrastructure cost per tenant;
- error rate and latency.

## Security baseline

- tenant isolation by design;
- permission checks for every business action;
- protection against IDOR vulnerabilities;
- secure cookies and HTTPS;
- rate limiting;
- audit trail;
- least-privilege database and storage access;
- encryption for secrets;
- backup and restore process;
- logged support access to customer data.

Frontend filtering must never be the only tenant isolation mechanism.

## Operations baseline

Required environments:

- local;
- development;
- staging;
- production.

Required operational capabilities:

- CI checks;
- database migrations;
- seed data;
- centralized logs;
- metrics;
- tracing;
- error tracking;
- health checks;
- backups;
- restore drills;
- feature flags;
- maintenance mode.

## Discovery dependency

The actual CRM code and database schema must be analyzed before selecting the
final framework, data model, and migration strategy.
