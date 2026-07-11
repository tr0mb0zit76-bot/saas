# ADR-011: AI / LLM data in multi-tenant SaaS

**Status:** Accepted  
**Date:** 2026-07-11

## Context

Traklo Pro runs **one shared LLM provider pool** (OpenAI / Azure / DeepSeek — vendor API keys), but **conversation history, embeddings, feedback, and agent audit** must never leak between tenants.

Current v5 tables (no `tenant_id`):

- `agent_conversations`, `agent_conversation_messages`
- `ai_conversations`, `ai_messages`, `ai_attachments`
- `ai_feedback_log`, `ai_knowledge_index`, `ai_order_drafts`, `ai_parser_logs`

## Decision

### 1. Data plane — always tenant-scoped

| Data | Rule |
| --- | --- |
| Conversations / messages | `tenant_id` FK on all AI tables (Tier A migration) |
| User prefs (`users.ai_preferences`) | Already under tenant via `users.tenant_id` |
| Embeddings / RAG (`ai_knowledge_index`) | `tenant_id` + vector namespace `{tenant_id}:*` |
| Agent tool invocations | Log `tenant_id`, `user_id`, `agent_slug` — no cross-tenant tool args |
| Attachments to LLM | Store in `TenantStorage` paths only; reference by key |

### 2. Control plane — shared LLM, isolated prompts

- **One vendor API key pool** — tenants do not bring their own keys on Start/Pro.
- **Enterprise optional:** BYOK (`tenants.settings.llm.provider_key`) — future.
- Every LLM request tagged in logs: `tenant_id`, `feature` (command_bar, mail_ai, …).
- **`ExternalLlmPayloadSanitizer`** — mandatory before outbound call; strip PII per profile.
- **Rate limits per tenant** — `tenant_usage_logs` (tokens/day, requests/min).

### 3. No per-tenant model hosting on MVP

Shared models (GPT-4o-mini / etc.). Isolation is **data + prompt context**, not separate fine-tunes.

Fine-tune / dedicated deployment — Enterprise SKU only, ADR amendment later.

### 4. Jobs and queues

All AI jobs carry `tenantId` in payload → `TenantContext::runAs()` at job start.

## Consequences

- Migration: add `tenant_id` to AI table group in same wave as mail/tasks.
- `BelongsToTenant` on AI Eloquent models when wrapped.
- CI: test tenant A conversation not visible to tenant B.
- Cost accounting: aggregate tokens by `tenant_id` for margin analysis.

## Alternatives rejected

- **Separate DB per tenant for AI only** — premature; revisit at >500 tenants with heavy AI usage.
- **Store prompts only in S3** — query/audit needs structured DB rows.
