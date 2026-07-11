# SaaS CRM for forwarding companies

This repository is the starting point for turning the existing single-company
CRM into a SaaS platform for forwarding and logistics companies.

## Current status

- GitHub remote: `https://github.com/tr0mb0zit76-bot/saas.git`
- Source CRM folders on the owner's local machine:
  - `C:\OSPanel\home\v5.local`
  - `C:\Sync\Yandex.Disk\Exchange`
- External sync folder for indexes and handoff (Yandex Disk, not in git):
  - `C:\Sync\Yandex.Disk\Exchange\saas`
- This Cloud Agent runs in a remote Linux environment with access only to the
  cloned GitHub repository at `/workspace`, not to the local Windows disk.

## Repository layout

```text
docs/                 Architecture, discovery, data, product, ADRs, handoff
apps/web/             Future tenant and platform web UI
apps/api/             Future backend/API service
apps/worker/          Future background jobs and integrations
packages/             Shared domain/config packages
infra/                Docker, CI, deployment notes
scripts/              Discovery, migration, maintenance scripts
tests/                E2E, integration, and fixtures
```

## Start here

1. Read `docs/00-handoff/current-state.md`.
2. Review `docs/02-architecture/saas-architecture.md`.
3. Mount or copy the CRM sources from `C:\OSPanel\home\v5.local`.
4. Fill the discovery indexes in `docs/01-discovery/`.
5. Keep exchange indexes and handoff notes in
   `C:\Sync\Yandex.Disk\Exchange\saas` via Yandex Disk sync. That folder is
   outside git and is intended for Obsidian, Hivemind, and similar tools.

## Development environment

The initial environment is intentionally stack-neutral because the source CRM
technology is not available yet. See:

- `docs/02-architecture/development-environment.md`
- `infra/docker/compose.dev.yml`
- `.env.example`

The default local services prepared for future code are PostgreSQL, Redis, and
Mailpit.
