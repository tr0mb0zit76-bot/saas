# ADR 0003: Migration discovery first

## Status

Accepted.

## Context

The current CRM source code, database schema, and knowledge files were not
available in the cloud environment. Writing application code before discovery
would risk rebuilding the wrong domain model.

## Decision

Complete discovery before implementation:

1. Source tree inventory.
2. Database schema inventory.
3. Business module map.
4. API and route index.
5. File and document storage inventory.
6. Company-specific logic review.
7. Migration map.

## Consequences

- The first repository changes are documentation and environment scaffold only.
- Implementation starts after real CRM facts are available.
- Migration scripts must be designed from discovered source data, not guesses.
