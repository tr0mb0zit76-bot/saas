# Source inventory

## Status

The source CRM folder `C:\OSPanel\home\v5.local` is not available in the current
cloud environment. This document is a template for the first discovery pass.

## Required source tree index

Fill this section after the CRM sources are mounted or copied.

```text
Root path:
Total files:
Total size:
Primary language:
Framework/CMS:
Database:
Package managers:
Entrypoints:
```

## Directories to identify

```text
app/controllers
app/models
app/views
config
database
migrations
public
routes
storage
uploads
cron
jobs
templates
vendor
node_modules
```

Use the actual project structure instead of these examples when available.

## Files to classify

| Area | Files | Notes |
| --- | --- | --- |
| Entrypoints | TBD | Web and CLI entrypoints |
| Routing | TBD | HTTP routes and API endpoints |
| Models | TBD | Business entities |
| Controllers | TBD | Request handlers |
| Views/templates | TBD | UI and document templates |
| Config | TBD | Must redact secrets |
| Jobs/cron | TBD | Background processes |
| Uploads/files | TBD | Storage migration required |
| SQL/migrations | TBD | Database discovery |

## Company-specific code to search for

- hardcoded company names;
- legal details;
- phone numbers and emails;
- fixed document templates;
- fixed sender/recipient accounts;
- absolute local paths;
- SQL without tenant boundary;
- static statuses that should become tenant settings;
- credentials in config files.

## Discovery output expected

After analysis, create:

- `docs/01-discovery/current-crm-technology.md`;
- `docs/01-discovery/current-crm-map.md`;
- `docs/01-discovery/database-inventory.md`;
- `docs/01-discovery/business-processes.md`;
- `docs/01-discovery/risks-and-gaps.md`.
