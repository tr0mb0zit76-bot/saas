# ADR-016: Print placeholder dictionary (platform + tenant aliases)

**Status:** Accepted  
**Date:** 2026-10-15

## Context

Tenants must configure their own DOCX print templates. Arbitrary data paths are unsafe; a platform catalog already exists (`PrintFormVariableCatalog`). Product decision: variant B — built-in mapping plus tenant macros mapped only from the catalog allowlist. No seed DOCX files for new tenants.

## Decision

1. **Platform catalog** is the source of truth for allowable paths.
2. **Per-template** `settings.variable_mapping` remains the template-level override UI (existing).
3. **Tenant aliases** live in `tenant.settings.print_placeholder_aliases` as `{macro, path}` rows; path must be in the catalog. Managed at Настройки → Шаблоны → Свои плейсхолдеры.
4. Resolution order in `PrintFormPlaceholderPathResolver`: template mapping → tenant alias → platform legacy aliases → raw placeholder.
5. **No DOCX seed** for new tenants — upload-your-own.
6. Print settings routes require `feature:print` (Pro+).

## Consequences

- Admins get short macros without inventing CRM field paths.
- Unknown paths rejected at save time.
- Start plan has no print UI (menu + middleware).
