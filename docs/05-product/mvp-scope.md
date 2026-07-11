# MVP scope

## MVP objective

Deliver the smallest SaaS CRM foundation that can onboard the first forwarding
company tenant and run core operational workflows safely.

## In scope

### Platform

- tenant creation;
- tenant profile;
- subscription status placeholder;
- platform admin access;
- platform audit events.

### Identity and access

- user registration or invitation;
- tenant membership;
- RBAC;
- permissions for core modules;
- session management;
- audit log.

### Tenant CRM

- customers and contacts;
- carriers;
- transportation orders;
- basic document records;
- file attachments;
- basic finance fields on orders;
- status history;
- tenant settings.

### Migration

- initial import from current CRM into one tenant;
- repeatable migration scripts;
- validation reports.

### Operations

- local development environment;
- database migrations;
- seed data;
- CI checks;
- health check endpoint;
- basic logs.

## Out of scope for MVP

- full billing automation;
- EDI integration;
- accounting integrations;
- mobile applications;
- customer and carrier external portals;
- advanced analytics;
- dedicated enterprise tenant infrastructure.

## MVP success criteria

- A new tenant can be created.
- Users can be invited and assigned roles.
- Tenant users cannot access another tenant's data.
- Customers, carriers, and orders can be managed.
- Documents can be attached to orders.
- The current CRM data can be imported into the first tenant.
- Basic operational reports can be generated.
