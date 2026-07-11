# Migration map

## Purpose

Map the current single-company CRM entities to SaaS entities and identify which
data must become tenant-scoped.

## Status

The current CRM database schema is not available yet. The table below is a
working template.

| Current entity/table | Target SaaS entity | Tenant scoped | Migration rule | Risk |
| --- | --- | --- | --- | --- |
| users | users, tenant_users | Yes | Split identity from tenant membership | Role semantics may differ |
| clients | customers | Yes | Attach to initial tenant | Duplicates and legal details |
| orders | orders or shipments | Yes | Preserve numbers, statuses, dates | Status mapping required |
| carriers | carriers | Yes | Attach carrier profile to tenant | Shared carrier directory decision |
| documents | documents | Yes | Move files to tenant storage | Missing files or paths |
| invoices | invoices | Yes | Preserve sums and VAT mode | Financial reconciliation |
| payments | payments | Yes | Link to invoices/orders | Partial payments |
| settings | tenant_settings | Yes | Convert hardcoded company values | Hidden assumptions |

## Migration phases

### 1. Extract

- source database dump;
- uploaded files;
- document templates;
- current configuration without secrets;
- user and role definitions.

### 2. Transform

- normalize entity names;
- add `tenant_id`;
- convert company-specific values to tenant settings;
- map roles to SaaS permissions;
- map statuses to SaaS workflow states;
- normalize file references.

### 3. Validate

- record counts;
- financial totals;
- relations between customers, orders, documents, invoices, and payments;
- document file existence;
- user access expectations;
- status history consistency.

### 4. Load

- create tenant;
- import users and memberships;
- import dictionaries and settings;
- import business records;
- import files;
- run validation reports.

## Required migration tooling

Migration scripts should be idempotent and should support:

- dry run;
- progress logs;
- resumable batches;
- validation-only mode;
- anonymized test fixtures;
- rollback plan for failed imports.
