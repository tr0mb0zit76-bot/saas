# ADR 0001: Repository structure

## Status

Accepted.

## Context

The SaaS repository starts before the source CRM is available for direct
analysis. The structure must support architecture, discovery, migration, and
future application code without assuming a confirmed stack.

## Decision

Use this top-level structure:

```text
docs/
apps/
packages/
infra/
scripts/
tests/
```

External sync and knowledge exchange live outside git at:

```text
C:\Sync\Yandex.Disk\Exchange\saas
```

## Consequences

- Documentation and handoff can move forward immediately.
- Future code has clear boundaries for API, web, workers, shared packages, and
  infrastructure.
- Yandex Disk sync folder remains separate from version control.
