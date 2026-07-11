# Decisions log

## 2026-07-11: Start documentation-first SaaS scaffold

Decision:

- Initialize the repository with architecture, discovery, migration, handoff, and
  development-environment scaffolding.

Reason:

- The source CRM folders are not accessible in the cloud environment.
- Final technology decisions require analysis of the current CRM code and
  database schema.

Consequences:

- Application code is intentionally not generated yet.
- Docker services are limited to generic infrastructure useful for most SaaS CRM
  stacks: PostgreSQL, Redis, and Mailpit.
- The sync folder `C:\Sync\Yandex.Disk\Exchange\saas` stays outside git and is
  synchronized through Yandex Disk for Obsidian, Hivemind, and handoff use.
