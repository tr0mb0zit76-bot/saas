# ADR-017: Tenant carrier matching (client request → Lead → staff share → carrier bid)

**Status:** Accepted  
**Date:** 2026-11-08  
**Supersedes / amends:** ADR-003 (module packaging) — adds all-plan helper; does **not** move full AA `load_board` out of Enterprise  
**Related:** ADR-006 (feature gating), ADR-008 (Traklo / external mobile SKU)

## Context

Product wants to expand the client cabinet so customers submit cargo parameters (route, dimensions, weight — the same shape used on the internal load board), then let the forwarder’s **known tenant carriers** bid a rate. This is framed as an **assistant to match known carriers**, not an open ATI-style market.

Product lock (2026-11-08):

1. Client request creates a **Lead only** — no auto-publish to the board.
2. The **responsible employee** manually “gives” cargo to carriers (optional: from a matching carrier list).
3. Audience is **tenant-only** carriers of that forwarder.
4. P0 carrier action is a **rate bid (ставка)**, not view-only.
5. Capability may ship on **all tariffs** as a helper — not Enterprise-quarantine only.
6. After a bid is accepted on the matching surface, the work **remains a Lead** until the responsible confirms; only then **Lead → Order**.

Existing code already has:

- `Lead` / `LeadCargoItem` / `LeadRoutePoint` — request spine (also `traklo_public_request`, mobile intake).
- `LoadBoardPost` / `LoadBoardOffer` — cargo snapshot, offers with rate/`carrier_id`, `lead_id`, staff `from_lead` prefill.
- `LeadRateQuote` (+ `load_board_offer_id`) — lead-side selected/received quotes.
- External cabinets: `MobileCounterpartyShellController` + token portals (`portal.carrier.*` / `portal.customer.*`).
- Feature `load_board` — Enterprise in ADR-003 / `config/saas-plans.php` (full биржа: ATI, advisor, procurement).

Tension: AGENTS.md and ADR-003 keep **full Load Board** out of MVP / Enterprise. Product now wants a **slim matching helper** on all plans without opening ATI or cross-tenant boards.

## Decision

### Domain flow

```
Customer (external) → Lead (+ cargo/route)
Staff responsible   → manually create share package from Lead
                    → pick tenant carriers (manual multi-select in P0)
Carrier (external)  → rate bid on share package
Staff               → select bid → sync onto Lead (LeadRateQuote / performers)
Staff later         → confirm Lead → Order (existing conversion)
```

### Entity choice (Ponytail)

**Reuse `LoadBoardPost` + `LoadBoardOffer` as the technical matching / share surface.**

- Staff action (not client submit) creates `LoadBoardPost` from Lead via existing `prefillFromLead` / `from_lead` path (extracted as a publisher service when built).
- Invited carriers = tenant contractors on the post (`metadata.carrier_pool_candidates` and/or explicit invite rows — prefer extending the existing pool pattern before a new table).
- Carrier bid = `LoadBoardOffer` with `carrier_id` = external user’s `contractor_id`, new offer source (e.g. `traklo_carrier`), authZ: carrier only creates/updates own offers on posts they were invited to.
- On staff select/accept of an offer for this helper flow: write selected rate onto **`Lead.performers` / `calculated_cost`** (+ metadata) — **do not** create/update `Order` until explicit Lead→Order conversion. (`LeadRateQuote` is not required for P0.)
- Client cabinet writes cargo onto **Lead** (enrich `LeadCargoItem` toward board field parity over time). Token customer portal stays docs-oriented unless separately scoped.

**Do not** invent `ClientRequest` / `FreightOffer` roots.

### Packaging / feature gates

| Capability | Feature key | Plans |
| --- | --- | --- |
| Slim tenant carrier matching (staff share + carrier bid + lead sync) | **`carrier_matching`** (new) | **Start, Pro, Enterprise** |
| Full internal биржа (ATI prepare, advisor, procurement cases, corridor insights, CRM buyer desk UI) | **`load_board`** (existing) | **Enterprise** (unchanged) |
| External party login / mobile shell | **`traklo_mobile`** | Per ADR-008 / plans (external UX); staff-side matching UI must work without requiring every carrier to use mobile if a web/token path is added later |

- Menu: helper entry near Leads / “Подбор перевозчика” — **not** the Enterprise “Биржа” IA for Start/Pro.
- Same tables may back both features; **route/UI/middleware** stay split so Start never gets ATI/advisor/procurement.
- Carrier-facing bid APIs require tenant context + invite check; never cross-tenant.

### Carrier visibility

- Default: posts created for matching are visible only to **invited** tenant carriers (`carrier_visibility = invited_pool`).
- No “all carriers on the internet”; “all tenant carriers” is an optional later toggle, still same `tenant_id`.
- Redact customer commercial fields in carrier payload as needed (privacy review before pilot).

### Contractor matching profile (follow-on, not P0)

- P0 matching = **manual multi-select** from tenant carrier contractors (search/filter by name/INN).
- Later: on `Contractor` (carrier), record typical **routes / corridors** and **vehicles / body types**; staff “matching list” becomes a ranked suggestion over that profile. Does not block P0.

## Consequences

### Positive

- Reuses cargo + offer machinery; Lead remains the commercial source of truth until Order.
- All-plan helper without selling AA ATI market.
- Clear split: `carrier_matching` vs Enterprise `load_board`.
- Fits existing `LeadRateQuote.load_board_offer_id` bridge.

### Negative

- One physical `load_board_posts` table serves two product surfaces — needs careful status/UX labeling (“подбор” vs “биржа”).
- Current `LoadBoardController::approveOffer` applies to Order — helper flow must branch (Lead-only accept).
- External carrier bid expands attack surface (IDOR on posts/offers) — requires saas-security + Feature/Saas tests before any non-demo tenant.

### Mitigations

- Feature middleware and menu separation; no ATI routes under `carrier_matching`.
- Accept-offer path: if `order_id` null and helper context → LeadRateQuote only.
- Isolation tests: tenant A carrier never sees tenant B posts; non-invited carrier 403.

## Non-goals

- Cross-tenant / public national load board.
- ATI integration, advisor, procurement-case desk as part of this helper.
- Auto-creating `LoadBoardPost` when a client submits a request.
- Auto Lead→Order on bid accept.
- Carrier matching profile / auto-rank as a P0 dependency.
- Replacing token `portal.carrier.*` trip-document flow (assigned order docs stay as today).

## Alternatives considered

1. **LeadRateQuote-only, no LoadBoardPost** — lighter conceptually, but forces a second cargo DTO for carrier-facing share and reimplements pool/invite. Rejected for P0 (Ponytail: reuse Post as share envelope).
2. **New `ClientRequest` / `FreightOffer` entities** — duplicates Lead + Offer. Rejected.
3. **Enable full `load_board` on all plans** — pulls ATI/Enterprise surface into Start. Rejected; split flags instead.
4. **View-only carrier cabinet in P0** — product requires rate bid. Rejected.

## Implementation sketch (after “go”)

1. ADR accept + add `carrier_matching` to `config/saas-features.php` / all plans in `config/saas-plans.php`.
2. Client cabinet: structured cargo → Lead.
3. Staff: “Share with carriers” from Lead → create Post + invited pool (manual pick).
4. Carrier shell: list invited open posts; submit `LoadBoardOffer` bid.
5. Staff select → `LeadRateQuote` + Lead update; convert to Order unchanged/existing path.
6. Tests: tenant isolation, invite gate, no Order on accept.
7. Follow-on ADR/note: contractor route/vehicle profile for suggest list.
