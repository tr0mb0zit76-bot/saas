# ADR-019: Lead «Предрасчёт» = транспортная цена клиенту (не растаможка)

**Status:** Accepted  
**Date:** 2026-11-09  
**Amends:** ADR-018 (client quote lifecycle) — clarifies staff workspace vs send/agree SoT  
**Related:** ADR-003 (module packaging), ADR-006 (feature gating), ADR-017 (carrier matching)  
**Team input:** saas-security · saas-qa · saas-privacy (2026-11-09)

## Context

Product meaning of **предрасчёт** for a freight-forwarder CRM: tell the **client the transport price**, not the goods + customs landed cost.

What shipped (v5 AA pattern carried into SaaS Lead wizard):

- Tab **«Предрасчёт»** (`LeadWizardPrecalculationTab.vue`) is an **import-cost / TN VED / duty-VAT** calculator: `goods_lines`, freight-to-border allocation, commercial hint «товары по ТН ВЭД…».
- Backend: `LeadPrecalculationService` + routes `leads.precalculation.*` (TN VED search, calculate, HTML/PDF) live under **leads** auth, **not** behind `feature:import_cost`.
- Dedicated module **«Растаможка»** (`modules/import-cost`, `feature:import_cost`, visibility `modules_import_cost`) already exists and is **Enterprise-only** in `config/saas-plans.php`.
- ADR-018 already locked the **client-facing** offer on Lead: `quoted_price` / `quote_status` / send → agree → Order, staff UI primarily on **«Финансы»**.
- Bug in current bridge: «Подставить в финансы» writes landed `grand_total` into **`target_price`** (ADR-018: client orientation), not into draft **`quoted_price`**.

PO / Orientum feedback (2026-11-09): customs UI on Predрасчёт is wrong scope; belongs to Растаможка. Related intake symptoms (sibling): cargo request fills «Суть сделки»; **Маршрут** empty; **Груз** duplicates main description; predрасчёт grid / recalculate icon broken; remove TN VED commercial hint.

## Decision

### 1. Semantics (locked)

| Concept | Meaning | SoT / UI |
| --- | --- | --- |
| **Предрасчёт** (Lead tab) | Staff workspace to assemble **transport** cost → **client transport price** | Legs / performers / service lines → cost; push into ADR-018 draft |
| **Оферта клиенту** | Price we offered | `quoted_price` + `quote_status` (ADR-018); **Финансы** owns send/agree |
| **Ориентир клиента** | What the client wants to pay | `target_price` — **never** overwritten by customs landed total |
| **Растаможка** | Goods + TN VED + duty/VAT/util landed cost | Module `import_cost` only |

Do **not** treat Lead `precalculation` JSON (goods/TN VED/landed) as the product «предрасчёт» for SaaS MVP.

### 2. TN VED / customs UI — keep / move / hide

| Surface | Verdict |
| --- | --- |
| Lead tab «Предрасчёт» default | **Hide** goods lines, TN VED search, customs totals, commercial TN VED hint |
| Lead routes `leads.precalculation.tn-ved.*` / goods calculate path | **Gate** with `feature:import_cost` (same as module) or remove once UI moves |
| Module «Растаможка» (`modules/import-cost`) | **Keep** as canonical customs calculator |
| Optional later | Deep-link / embed customs calc from Lead **only if** `Tenant::featureEnabled('import_cost')` — still labeled Растаможка, not Предрасчёт |
| Existing `leads.precalculation` JSON with goods_lines | **Preserve** on read for AA/Orientum historical data; do not require for transport flow; snapshot-on-convert may stay for legacy rows |

### 3. Recommended UX (transport quote)

Canonical staff path:

```
Маршрут + Груз (facts)
  → Плечи / performers / ставки перевозчиков (cost)
  → Предрасчёт: service lines + margin → draft client price
  → Финансы: quoted_price draft → «Ответить клиенту» → agreed → Order
```

MVP UX rules:

1. Predрасчёт copy: transport price for client (no TN VED / landed-cost language).
2. «Подтянуть с плеч» / performers → service lines stays; recalculate stays in-tab (fix float).
3. «Подставить в оферту» → **`quoted_price` draft** (+ optional `calculated_cost` from services), **not** `target_price`.
4. Send/agree remain on Финансы / ADR-018 APIs (`presentLeadForCustomer` whitelist unchanged).
5. Customer / carrier DTOs never get goods_lines, duty, invoice value, margin breakdown, or draft quote.

### 4. MVP cut

**Now (Orientum / pilot):**

1. Retarget Predрасчёт UI copy + remove TN VED hint; hide goods/TN VED grid unless `import_cost`.
2. Gate lead TN VED / customs calculate routes with `feature:import_cost`.
3. Fix apply-to-finance → `quoted_price` draft (not `target_price`).
4. Fix grid / recalculate icon layout (cosmetic P0 for staff trust).
5. Sibling intake: map cabinet loading/unloading into Маршрут `route_points` reliably; stop copying deal description into cargo description (places/weight/dims OK).
6. Tests that must go red on regress (saas-qa): feature gate Start without `import_cost` → 403 on lead customs path; transport default calculate without goods; ClientLeadQuoteLifecycleTest must stay green.
7. Pilot Orientum: force `settings.features.import_cost = false` even if plan is enterprise / full catalog was enabled — Predрасчёт stays transport-only; Растаможка module stays off until intentionally re-enabled.

**Later:**

1. Full redesign of Predрасчёт as legs-rate builder (multi-currency, templates).
2. Heuristic staff hint into draft (ADR-018 §6b).
3. Optional Lead→Растаможка deep link when feature on.
4. Migrate / drop legacy goods_lines schema once no tenants need AA landed snapshot on Lead.
5. Tenant export/delete coverage for `precalculation` when customs returns (privacy).

### 5. Packaging

Unchanged: `import_cost` = Enterprise (Растаможка). Transport Predрасчёт + ADR-018 quote lifecycle ship with **leads** / `carrier_matching` — not tied to customs.

**Pilot exception (2026-11-09):** Orientum keeps `import_cost` off via tenant settings override even if plan is enterprise, so Predрасчёт customs blocks stay hidden for the pilot.

## Consequences

### Positive

- Product language matches ICP (expeditor sells carriage, not customs brokerage by default).
- Closes P1 feature-gating leak: Start/Pro no longer get TN VED calculator via Lead.
- Aligns staff workspace with ADR-018 without a new Quote entity.
- Shrinks commercial-secret surface on Lead for tenants without Растаможка (privacy).

### Negative

- AA-style landed-cost-on-Lead UX regresses for staff who used the tab as customs desk — mitigated by Enterprise module.
- Legacy `precalculation.goods_lines` and unit tests (`LeadPrecalculationServiceTest`) need retarget or split.

### Mitigations

- Security: gate routes + UI; do not expand customer/carrier DTO.
- QA: add SaaS feature + transport-default asserts; do not rely on ClientLeadQuoteLifecycleTest alone.
- Privacy: customs data only when `import_cost` on; no new PII class from TN VED codes alone.

## Alternatives considered

1. **Rename Predрасчёт → Растаможка on Lead** — Rejected: duplicates module; wrong default for Start/Pro.
2. **Merge Predрасчёт into Финансы only** — Rejected for MVP: staff still need a cost-build workspace; keep tab, change semantics.
3. **Keep dual calculator always visible** — Rejected: wrong scope + feature leak.
4. **New Quote aggregate** — Rejected (ADR-018).

## Product locks (closed 2026-11-09)

1. Predрасчёт = **transport price to client**, not goods+customs landed cost.
2. TN VED / customs UI and lead customs APIs → **`import_cost` / Растаможка** only.
3. Client offer SoT remains ADR-018 (`quoted_price` / send / agree); Predрасчёт feeds draft, does not bypass send gate.
4. Never write customs/transport calculator output into `target_price`.
