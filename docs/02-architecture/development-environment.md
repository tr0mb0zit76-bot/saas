# Development environment

## Current approach

The repository starts with a stack-neutral development environment because the
source CRM technology is not available yet. This avoids locking the project into
a framework before the current CRM code and database are analyzed.

## Prepared local services

`infra/docker/compose.dev.yml` defines:

- PostgreSQL for application data;
- Redis for queues, cache, sessions, or rate limiting;
- Mailpit for local email testing.

## Expected future app layout

```text
apps/api      Backend API
apps/web      Web UI
apps/worker   Background jobs and integrations
packages/*    Shared domain, config, and utility packages
```

## Local environment variables

Copy `.env.example` to `.env` when local development starts.

```bash
cp .env.example .env
```

## Start local infrastructure

```bash
docker compose -f infra/docker/compose.dev.yml up -d
```

## Stop local infrastructure

```bash
docker compose -f infra/docker/compose.dev.yml down
```

## Required decisions before application code

1. Confirm the current CRM technology stack.
2. Confirm the current database engine and schema.
3. Decide whether to evolve the current stack or build a new service around the
   migrated domain model.
4. Choose the application framework.
5. Define database migration tooling.
6. Define CI checks for the chosen stack.

## Environment principles

- All services must be reproducible from repository files.
- Local setup must avoid production secrets.
- Database migrations must be versioned.
- Seed data must be synthetic.
- Scripts must be idempotent and safe to rerun.
