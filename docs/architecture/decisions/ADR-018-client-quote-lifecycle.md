# ADR-018: Client quote lifecycle (просчёт) — Lead SoT, matching as tool

**Status:** Accepted  
**Date:** 2026-11-09  
**Accepted locks:** 2026-11-09 (PO)  
**Amends:** ADR-017 (tenant carrier matching) — does **not** reopen Enterprise `load_board` to Start/Pro  
**Related:** ADR-006 (feature gating), ADR-010 (fail-closed tenant scope), privacy minimization for carrier DTO  
**Amended by:** ADR-019 — Lead «Предрасчёт» = transport quote workspace (not TN VED / customs); customs stays under `import_cost`

## Context

ADR-017 locked: client cabinet → **Lead only** → staff invite share → carrier bid on matching `LoadBoardPost` / `LoadBoardOffer` → accept writes cost onto Lead → Order only later.

Product friction (2026-11-09): the chain feels bulky — «просчёт → Lead → fork (seller prices somehow / board) → somehow return price to client». Floated alternative: request lands on board first → price → reply + form Lead. Also future: invite external carriers; historical stats → heuristic client quotes.

Code facts:

- Client API (`presentLeadForCustomer`) returns status + cargo, **no commercial offer price**.
- Accept path sets `Lead.calculated_cost` / `performers` from **carrier** rate — that is cost, not client-facing quote.
- `Lead.target_price` already exists — PO lock: this is the **client’s desired price orientation** (what they want to pay), **not** our offer.
- Matching posts share tables with Enterprise биржа; invite list lives in `metadata.carrier_matching`.
- Security/QA/Privacy: open board-first + auto-broadcast + cross-tenant heuristics are **not** pilot-safe.

## Decision

### 1. Source of truth

| Concern | SoT | Client-visible? |
| --- | --- | --- |
| Request / commercial case | **`Lead`** (`traklo_customer_request*`) | Yes (whitelist DTO) |
| Client’s desired budget | **`Lead.target_price`** (+ `target_currency`) | Optional later; **not** our offer |
| Price **we offered** the client | **`Lead.quoted_price`** (+ `quoted_currency`) | Only when `quote_status` ∈ {`sent`, `agreed`} |
| Quote lifecycle | **`Lead.quote_status`** + timestamps | Yes (status label); amount only after send |
| Carrier share + bids | Matching **`LoadBoardPost`** + **`LoadBoardOffer`** | **Never** |
| Carrier cost / margin | `calculated_cost`, `performers`, offer rows | Staff only |
| Order | Existing Lead → Order | **Only after** quote agreed |

Do **not** invent a root `QuoteRequest` / `FreightOffer` entity for MVP. Do **not** make orphan `LoadBoardPost` (no Lead) the client request. Do **not** reuse `target_price` as our offer.

### 2. Field naming (locked)

On `leads` (new columns; not metadata-only — need query/filter and conversion clarity):

| Column | Type | Meaning |
| --- | --- | --- |
| `quoted_price` | decimal nullable | Commercial price **we** offered the client |
| `quoted_currency` | string, default RUB | Currency of `quoted_price` |
| `quote_status` | string enum | See state machine |
| `quote_sent_at` | timestamp nullable | When staff sent the offer to the client |
| `quote_agreed_at` | timestamp nullable | When price was marked agreed |

**Why `quoted_price` (not `client_quote` / `offered_price`):**

- `target_price` = client orientation (already taken).
- `offered_price` collides mentally with carrier `LoadBoardOffer`.
- `client_quote` is ambiguous (their quote vs ours).
- `quoted_price` = «цена, которую мы процитировали клиенту».

### 3. Quote state machine (locked)

```
none ──► draft ──► sent ──► agreed ──► (Lead → Order)
                 │         │
                 │         └──► rejected / withdrawn (optional MVP+)
                 └── (edit stays draft until send)
```

| Status | Who sets | Client sees amount? | Notes |
| --- | --- | --- | --- |
| `none` | default | No | No offer started |
| `draft` | staff saves quote | **No** | Staff-only; may change freely |
| `sent` | staff «Ответить клиенту» / send | **Yes** | Sets `quote_sent_at`; drives client cabinet |
| `agreed` | **staff** marks agreement | **Yes** | Sets `quote_agreed_at`; **gate before Order** |
| `rejected` / `withdrawn` | staff (MVP+ ok) | No new amount; prior `sent` may stay historical | Optional; not required for first slice |

**Actor rules (PO lock):**

1. Staff forms the commercial offer (`draft` → amount).
2. Staff **sends** to client (`sent`).
3. Price **agreed** (staff-driven; client does not invent the offer).
4. **Then** Lead → Order conversion.

Client may later get a thin «принять / отклонить» UX that only signals staff — they still do not author `quoted_price`. MVP may mark `agreed` staff-side only.

### 4. Simplified product flow (canonical)

```
Client        → submits просчёт → Lead (quote_status=none; optional target_price = их ориентир)
Seller/Buyer  → Path A: staff sets quoted_price (draft) from experience / heuristic hint
              → Path B: shareLeadWithCarriers → bids → pick cost → markup → quoted_price (draft)
Staff         → «Ответить клиенту» → quote_status=sent (+ quote_sent_at)
Client        → sees quote_status + quoted_price (never carrier_rate / calculated_cost / draft)
Staff         → mark agreed → quote_status=agreed (+ quote_agreed_at)
Staff         → Lead → Order (blocked unless agreed — enforce in conversion)
```

UX may say «на доске подбора» for Path B; technically Path B is still Lead + matching post, not an open marketplace.

### 5. Rejected for MVP

- True **board-first** create of `LoadBoardPost` without Lead.
- Auto-publish to all carriers / internet.
- Showing carrier bids or `calculated_cost` in customer cabinet.
- Showing `quoted_price` while `quote_status=draft`.
- Reusing `target_price` as our offer.
- Client inventing / authoring the commercial offer.
- Cross-tenant / platform-wide raw rate pools for client auto-quotes.
- Merging Start/Pro matching UI into Enterprise `load_board` desk.
- **External carriers on the board** — **after** client-facing quote lifecycle ships (later slice), not before.

### 6. Later hooks (gated)

**(a) External carriers on board** — only after MVP quote lifecycle (send + visibility + agreed → Order). Invite token bound to `tenant_id` + `contractor_id`; carrier-minimized DTO; feature-gate after isolation tests.

**(b) Heuristic client quote** — tenant-scoped aggregates only; suggest into **staff** UI as hint into `quoted_price` draft; never auto-send to client; no cross-tenant raw observations.

### 7. Packaging

Unchanged from ADR-017: `carrier_matching` (Start/Pro/Enterprise) vs `load_board` (Enterprise). Quote send/agree is part of `carrier_matching` / client cabinet, not Enterprise биржа.

## Consequences

### Positive

- Clear split: `target_price` (client wants) vs `quoted_price` (we offer).
- One mental model: **Lead = просчёт**; board = optional pricing tool.
- Explicit send gate prevents draft leak to customer DTO.
- Order gated on `agreed` matches commercial reality.

### Negative

- Extra columns + staff UI (form / send / agree) — necessary product work.
- Same physical `load_board_posts` table still needs matching vs биржа labeling.
- Conversion service must learn the `agreed` gate.

### Mitigations

- Customer DTO: include `quoted_price` / currency **only if** `quote_status` ∈ {`sent`, `agreed`}; always omit offers / `calculated_cost` / performers / matching metadata.
- Feature tests: draft not visible; after send visible; client never sees carrier rates; Order blocked until agreed.
- Carrier DTO minimization before any external invite (privacy).
- Heuristics: staff-only until k-floor + tenant scope in `tests/Feature/Saas`.

## Alternatives considered

1. **Board-first, Lead later** — Rejected for MVP (orphan ownership / presenter bleed).
2. **New `QuoteRequest` aggregate** — Rejected (duplicates Lead).
3. **Reuse `target_price` as our offer** — Rejected by PO: `target_price` = client orientation.
4. **Auto-reply heuristic to client** — Rejected until staff-hint path proven.
5. **Client self-accept creates Order** — Rejected by PO: staff drives send + agreement, then Order.
6. **External carriers before quote lifecycle** — Rejected by PO: later slice.

## Product locks (closed 2026-11-09)

1. **Separate field** `quoted_price` (+ currency/status/timestamps) — not `target_price`.
2. **Flow:** staff forms → sends → agreed → **then** Order; client does not invent the offer.
3. **External carriers:** after quote lifecycle MVP, not before.

## Implementation sketch (next coding slices)

### MVP slice 1 — client-facing quote lifecycle

1. Migration: `quoted_price`, `quoted_currency`, `quote_status`, `quote_sent_at`, `quote_agreed_at` on `leads`.
2. Staff: save draft quote; action «Ответить клиенту» → `sent`; action «Цена согласована» → `agreed`.
3. `presentLeadForCustomer`: expose amount only when `sent`/`agreed`; never draft/cost/bids.
4. `LeadConversionService` (or gate): refuse Order unless `quote_status=agreed` for customer-request leads (or all leads once column exists — product default: gate when quote flow used / status ≠ `none`).
5. SaaS tests: draft hide; send visibility; no carrier rates; Order gate; tenant isolation unchanged.

### Later

6. Staff heuristic hint → draft `quoted_price`.
7. External carrier invite + minimized DTO.
