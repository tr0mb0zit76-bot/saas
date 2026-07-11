# SaaS CRM for forwarding companies

This repository is the starting point for turning the existing single-company
CRM into a SaaS platform for forwarding and logistics companies.

## Current status

- GitHub remote: `https://github.com/tr0mb0zit76-bot/saas.git`
- Source CRM folders requested by the owner:
  - `C:\OSPanel\home\v5.local`
  - `C:\Sync\Yandex.Disk\Exchange`
- These Windows folders are not mounted in the current Linux cloud environment.
  The repository therefore starts with architecture, discovery, handoff, and
  development-environment scaffolding.

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
exchange/saas/        Mirror structure for C:\Sync\Yandex.Disk\Exchange\saas
```

## Start here

1. Read `docs/00-handoff/current-state.md`.
2. Review `docs/02-architecture/saas-architecture.md`.
3. Mount or copy the CRM sources from `C:\OSPanel\home\v5.local`.
4. Fill the discovery indexes in `docs/01-discovery/`.
5. Use `exchange/saas/` as the repository copy of the Windows sync handoff
   folder. Its contents are intended to be copied or synchronized to
   `C:\Sync\Yandex.Disk\Exchange\saas`.

## Development environment

The initial environment is intentionally stack-neutral because the source CRM
technology is not available yet. See:

- `docs/02-architecture/development-environment.md`
- `infra/docker/compose.dev.yml`
- `.env.example`

The default local services prepared for future code are PostgreSQL, Redis, and
Mailpit.
