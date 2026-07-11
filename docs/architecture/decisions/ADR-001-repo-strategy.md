# ADR-001: Стратегия репозитория

**Статус:** Accepted  
**Дата:** 2026-07-11

## Context

CRM v5 (`v5.local`, `tr0mb0zit76-bot/v5`) — внутренний single-tenant продукт Автоальянса в production. SaaS — multi-tenant продукт для любых экспедиторов. Нужно решить: один репозиторий или два, как синхронизировать код.

## Decision

**Два независимых репозитория:**

| Repo | Назначение |
| --- | --- |
| `v5.git` | Внутренний CRM AA, prod, single-tenant |
| `saas.git` | Multi-tenant SaaS для экспедиторов |

**Bootstrap:** однократное копирование v5 → saas через `scripts/bootstrap-from-v5.ps1`.  
**Далее:** независимая разработка. Bugfixes в v5 — cherry-pick в saas по необходимости.

## Consequences

### Positive
- Prod AA не рискуется tenancy-рефакторингом
- SaaS может менять auth, billing, onboarding без влияния на AA
- Чёткое разделение ответственности

### Negative
- Два репозитория = двойной merge при общих фиксах
- Drift между кодовыми базами со временем

### Mitigations
- Документировать общие паттерны в ADR
- Isolation tests в saas обязательны
- Tenant #1 = AA — backfill, не live cutover до готовности

## Alternatives considered

1. **Tenancy в v5 master** — отклонено: риск для prod AA
2. **Monorepo с shared package** — отклонено: over-engineering на старте
3. **Greenfield SaaS** — отклонено: ~4000–6000 ч работы уже в v5
