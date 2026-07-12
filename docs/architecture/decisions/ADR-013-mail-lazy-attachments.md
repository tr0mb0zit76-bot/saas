# ADR-013: Lazy mail attachments and retention file purge

**Status:** Accepted  
**Date:** 2026-07-12

## Context

Mail IMAP sync imported attachment bodies eagerly (up to 10 × 15 MB per message). Primary CRM value from mail is **text analysis** (AI thread summary, insight drafts). Eager attachments inflate tenant storage; retention purged message bodies but left files on disk.

## Decision

1. **Default:** `MAIL_SYNC_IMPORT_ATTACHMENTS=false` — sync stores attachment **metadata only** (name, MIME, size, IMAP UID/part/folder).
2. **On-demand:** User download/preview triggers `MailLazyAttachmentFetcher` → IMAP fetch → `TenantStorage` → subsequent reads from disk.
3. **Eager mode:** `MAIL_SYNC_IMPORT_ATTACHMENTS=true` restores previous behavior (Pro/lab override).
4. **Retention purge:** `mail:purge-non-important-bodies` deletes stored attachment files and keeps name/size/MIME metadata alongside `retention_summary`.

## Consequences

- Lower storage use for SaaS tenants; sync faster.
- Download requires live IMAP + mailbox credentials; purged messages lose lazy IMAP refs (metadata names only).
- AI analysis unchanged — uses `body_text` / `retention_summary`.

## Related

- ADR-005 tenant file storage
- ADR-011 AI / tenant data
- `config/mail_sync.php`, `MailLazyAttachmentFetcher`, `MailMessageAttachmentJanitor`
