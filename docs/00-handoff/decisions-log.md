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

## 2026-07-11: Keep Yandex Disk sync folder outside git

Decision:

- Do not store `C:\Sync\Yandex.Disk\Exchange\saas` in the git repository.
- Document the external sync folder structure in `docs/00-handoff/external-sync.md`.

Reason:

- That folder is synchronized through Yandex Disk for Obsidian, Hivemind, and
  handoff/index exchange.
- Git is reserved for code, architecture docs, infra, and scripts.

Consequences:

- Removed `exchange/saas/` from the repository.
- Discovery indexes and handoff notes should be maintained on Yandex Disk, not
  in git.
