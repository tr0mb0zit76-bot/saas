# ADR-021: Fine-grained settings, content packs, print verification opt-in

**Status:** Accepted  
**Date:** 2026-11-14  
**Accepted:** 2026-11-14 (PO answers locked)  
**Related:** ADR-003 (module packaging), ADR-006 (feature gating), ADR-016 (print placeholders), ADR-015 / **ADR-022** (integrations connectors)  
**Team input:** saas-security · saas-qa · saas-privacy (2026-11-14; integrations follow-up same day)

## Context

Tenants do not want identical sales-book seed content, and not every tenant needs QR / unique-number verification on printed order documents. Today we only have:

| Layer | Mechanism | Examples |
| --- | --- | --- |
| Plan capability | `config/saas-plans.php` + `SubscriptionPlan` + platform checkboxes | `sales_book`, `print` |
| Tenant feature override | `tenants.settings.features` → `Tenant::featureEnabled()` | Orientum `import_cost=false` |
| Tenant behavior / content settings | other keys under `tenants.settings` | `branding`, `print_placeholder_aliases`, `onboarding` |

Gaps:

1. **Sales book** (`feature:sales_book`, Pro+) — articles are already `BelongsToTenant`, but there is no pack strategy: demos/tests create pages ad hoc; AA-style playbooks must not land on every new tenant.
2. **Print verification** — `OrderPrintDocumentWorkflowService` always mints `PrintFormVerificationCode` and may stamp PDF QR; DOCX inject is already placeholder-gated (`document_verification_qr` / empty code → no image), but there is no tenant opt-in. Public page exists under tenant host + HMAC (`PublicOrderDocumentVerificationController`).
3. Product keeps asking for knobs finer than module on/off; risk is inventing a second feature system that elevates plan rights or a shared content catalog that leaks across tenants.

## Decision

### 1. Layered model (reuse, no new framework)

| Layer | Purpose | Storage / enforcement | May elevate plan? |
| --- | --- | --- | --- |
| **Plan capability** | Billable / packaged module | Plan catalog + `feature:{key}` + menu | N/A (source of truth) |
| **Tenant feature override** | Platform narrow/widen of plan keys | `settings.features` map/list via `featureEnabled` | Widen only via platform admin; never via tenant self-serve |
| **Tenant setting** | Behavior inside an enabled module | `tenants.settings.{domain}.*` | **Never** |
| **Content pack** | Seedable copy-on-write data | Files/code templates → insert tenant-scoped rows; record installed ids in `settings.content_packs` | **Never** (data, not ACL) |
| **Connector instance** | Per-tenant external system binding (1C, …) | `settings.integrations[]` + encrypted secrets + in-repo `adapter_key` registry | **Never** — needs `feature:integrations`; see **ADR-022** |

Rules:

- Do **not** add Laravel Pennant, a parallel flag service, or plan checkboxes for every UX toggle / connector flavor.
- Tenant settings **only narrow or configure** behavior when the parent `feature:*` is on.
- Content packs are **not** shared runtime catalogs: runtime reads always go through `TenantScope` / `BelongsToTenant` rows.
- Connectors are **not** content packs: live credentials + sync jobs; packaging rules in ADR-022 (amends ADR-015).

### 2. Tenant classes (PO-locked)

| Class | Who | Print verification | Sales book packs on provision / cutover | Integrations (ADR-022) |
| --- | --- | --- | --- | --- |
| **(A) Cutover / pilot** | Orientum (Автоальянс → Ориентум inherits AA behavior); any future AA-mapped tenant | `settings.print.document_verification = true` (as today) | **Full seed:** `crm-user-guide` + `admin-guide` + `sales-playbook` («всё сеем») | May clone **Orientum-scoped** 1C connector config (not shared env); `feature:integrations` as deal requires |
| **(B) New external** | Any newly provisioned external tenant | `false` (opt-in later via platform) | **`crm-user-guide` only** | No connector unless Enterprise + platform provisions; never inherit AA/Orientum secrets |

Lab/demo seeders may install extra packs for local smoke; production class (B) stays minimal unless platform ops installs more.

### 2b. Integrations (pointer)

Canon for 1C / custom adapters / secrets / webhooks / jobs: **ADR-022**. Same platform-ops MVP rule as packs: platform admin provisions connectors; plan checkbox is only `integrations`, not which connector.

### 3. Sales book content packaging

- **Module:** keep `feature:sales_book` (Pro+). Empty book with module on is valid (edge); normal (B) provision installs user guide.
- **Packs (slugs):**
  - `crm-user-guide` — product how-to (default for class B).
  - `admin-guide` — admin/ops CRM guide.
  - `sales-playbook` — sales playbook content for cutover (class A); fictional examples only in platform templates.
- **Who installs packs (MVP):** **platform admin on request only** — not self-serve tenant admin. No tenant UI to install/remove packs in MVP.
- **Install path:** platform template (repo files / seeder source) → **copy** into `sales_book_articles` under current `tenant_id`. Record e.g. `settings.content_packs.sales_book = ['crm-user-guide', …]`.
- **Re-install / reset:** idempotent by pack slug + stable page keys; do not clobber tenant edits without explicit platform «reset pack» action.
- **Class A cutover:** install all three packs as part of Orientum (AA→Orientum) seed/migrate path so behavior matches «всё сеем».

Privacy (hard): packs contain **zero real PII**; fictional placeholders only. No prod AA people/contacts. Platform templates are not subject data; tenant copies + feedback/quiz are in export/delete scope.

### 4. Order print verification (QR / unique number)

- **Not** a plan feature. Parent module remains `feature:print`.
- **Tenant setting:** `settings.print.document_verification` (bool).
  - **Class (B) new external:** default `false`.
  - **Class (A) Orientum / AA-mapped:** `true` at migrate/provision (inherit AA).
- **Platform ops:** enable/disable verification for a tenant via platform tenant settings (same ops surface as pack install) — not plan feature checkboxes.
- **When `false` (fail-closed):**
  - Do not put verification code into print context.
  - Do not inject DOCX QR / code values (empty / skip).
  - Do not PDF-stamp verification QR; do not write verify URL into metadata for new generates.
  - Public verify for documents that still have a code: **404** when owner tenant has setting off (no soft «disabled» leak). Cross-tenant host remains 404 via existing scope.
- **Templates:** placeholders `${document_verification_qr}` and `${document_verification_code}` stay **optional** in the catalog (ADR-016). Templates without them reserve no space. Enabling verification without placeholders = code in metadata only until admin adds macros (no forced layout).
- **Public page allowlist (unchanged intent):** validity, order №/date, signature status, SHA-256, optional legal entity name. No ФИО/phones/emails/addresses/driver docs/routes/cargo/amounts/file body.

### 5. Platform ops path (MVP)

Platform admin (not tenant self-serve):

1. Set `settings.print.document_verification` true/false for a tenant.
2. Install content packs by slug into that tenant (copy-on-write); update `settings.content_packs.sales_book`.
3. Optional explicit reset of a pack (confirm; preserves policy vs overwrite).

Plan / tenant **feature checkboxes** stay module-level only (`sales_book`, `print`, …). Packs and verification are **not** in the plan feature matrix.

### 6. PO answers (closed)

| # | Question | Decision |
| --- | --- | --- |
| 1 | Print verification defaults | Orientum (AA cutover) **ON**; new external tenants **OFF** |
| 2 | Default sales book pack | Only **`crm-user-guide`** for class B |
| 3 | Who installs packs | **Platform admin on request** only (MVP) |
| 4 | `sales-playbook` timing | **Immediate for Orientum full seed** (user + admin + playbook); other tenants user-guide only unless platform installs |

## Consequences

### Positive

- External tenants get CRM user guide without AA playbooks or forced QR layout.
- Orientum keeps AA-like verification + full book after cutover.
- Clear rule stops «every preference becomes a plan feature».
- Pack install stays under platform control → fewer support accidents.

### Negative / debt

- Workflow/PDF paths must branch on the setting (today always mint/stamp).
- Pack install/reinstall needs idempotency and edit-preservation policy.
- CI must add Feature/Saas coverage (today only module-level gates).
- No tenant self-serve pack UI until a later ADR/amendment.

### Mitigations

- Single helper e.g. `Tenant::settingBool('print.document_verification', false)` reading `settings` — no new table in P0.
- Pack installer service with slug registry under `database/content-packs/sales_book/{slug}/` (or equivalent).
- Cutover seeder/migration explicitly sets Orientum verification `true` + full packs.
- Tests from team: pack isolation, setting≠feature, verify off omits QR, cross-host 404, Orientum-class fixture gets full seed.

## Alternatives considered

1. **Everything as `feature:*`** — rejected: pollutes plan matrix; verification is not a SKU; packs are not ACL.
2. **Shared platform sales-book catalog (FK to global articles)** — rejected: cross-tenant leak risk; conflicts with BelongsToTenant.
3. **Always seed full AA book; tenants delete** — rejected for class B; retained only as class A cutover full seed.
4. **Force QR block in every DOCX** — rejected: layout tax; ADR-016 already upload-your-own + optional macros.
5. **Tenant self-serve pack install in MVP** — rejected by PO; platform-on-request only.
6. **Schema-per-tenant content** — rejected for MVP; single DB + tenant_id packs suffice.

## Out of scope (this ADR)

- Product implementation beyond accepting this decision (coding is a follow-up when PO says go).
- Changing public verify HTML beyond allowlist confirmation.
- Tenant self-serve pack / verification UI.
- Sales scripts / trainer content packs (same model later if needed).

## P0 / P1 implementation sketch

### P0 (landed 2026-11-14)

1. Adopt `settings.print.document_verification`; default **false** on new external provision.
2. **Migrate Orientum** (and any AA-mapped tenant): `document_verification=true` + install full packs (`crm-user-guide`, `admin-guide`, `sales-playbook`).
3. Gate mint / DOCX inject / PDF stamp / public verify on setting (**fail-closed** when off).
4. Provision class B: install **`crm-user-guide` only** when `sales_book` on.
5. Platform ops: `platform.*/tenants/{id}/ops` — verification flag + pack install (no tenant self-serve).
6. Feature/Saas tests: `ContentPacksAndPrintVerificationTest` — pack isolation; class A vs B; verify off 404; setting ≠ `featureEnabled`.

### P1 (~1–2 weeks)

1. Hardened platform UI for pack install/reset with confirm.
2. Privacy review of pack markdown; export/delete includes tenant copies only.
3. Optional: catalog note «verification placeholders needed when setting on».
4. Revisit tenant self-serve only if PO opens it (amendment).
