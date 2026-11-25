# ADR-026: Tenant self-serve catalog connectors (Settings → Интеграция)

**Status:** Accepted (PO lock 2026-11-23)  
**Date:** 2026-11-23  
**Amends:** [ADR-022](ADR-022-per-tenant-integration-connectors.md) — adds tenant self-serve for allowlisted catalog adapters only  
**Extends:** ADR-015, ADR-021  
**Related:** ADR-006 (feature gating), ADR-010 (fail-closed tenant scope)

## Context

ADR-022 locked MVP connector bind to **Platform Ops** and forbade a tenant marketplace. PO now wants CRM **Настройки → Интеграция**: tenant **admin** can connect typical packaged systems (standard 1С OData, major banks) without a support ticket — without allowing arbitrary uploaded adapters.

## Decision

### 1. Partial self-serve (not a marketplace)

| Who | May bind |
| --- | --- |
| Tenant role **`admin`** + `feature:integrations` | Only adapters with `tenant_self_serve: true` in `config/saas-connectors.php` |
| Platform Ops | Any allowlisted `adapter_key` (including deal/custom and AA multi-pub template clone) |

Still forbidden: upload PHP/Composer, tenant-supplied FQCN, plan checkbox per connector, Start/Pro self-elevate.

### 2. Product vs AA packaging (1С)

- **Self-serve product:** `one_c_bp` — single OData publication, standard `standard.odata` entity paths.  
- **«Допилить» per tenant:** overlay `endpoints` / `mapping` on the connector instance (and later optional in-repo subclass with `tenant_self_serve: false`).  
- **AA multi-IB template** `aa_one_c_bp` — **Ops-only**, not in tenant catalog.

### 3. P0 catalog stubs (locked)

| `adapter_key` | Label | Auth model | Public docs | Notes |
| --- | --- | --- | --- | --- |
| `one_c_bp` | 1С (стандартная конфигурация) | `basic_odata` | yes (1С OData) | Single publication |
| `t_bank` | Т‑Банк (T-API) | `bearer_token` | [developer.tbank.ru](https://developer.tbank.ru) | OpenAPI |
| `tochka` | Точка | `oauth2_jwt` | [developers.tochka.com](https://developers.tochka.com) | Sandbox available |
| `sber` | Сбер API | `oauth2` | [developers.sber.ru](https://developers.sber.ru) | Statements/RKO |
| `alfa` | Альфа API | `oauth2_or_apikey` | [developers.alfabank.ru](https://developers.alfabank.ru) | Statements ЮЛ |
| `vtb_bank` | ВТБ ИБК | `ibk_soap_gost` | no full OpenAPI | Host-to-Host SOAP + ГОСТ + CryptoPro; onboarding checklist |

**Out of catalog:** Modulbank and other small/niche banks until a paying tenant asks.

All bank adapters ship as `runtime_status: stub` in P0. Live runtime is phased (prefer T-Bank first among banks; VTB last).

### 4. Settings IA

- Menu key `integrations` → `/settings/integrations`, label **«Интеграция»**.  
- Distinct from **«Связи MCP»** (`mcp-integrations` / `feature:mcp_read`).  
- Without `feature:integrations`: show menu item with **lock** + Enterprise upsell; mutations **403**.  
- Mutating routes: role **admin** only (not every `settings_system` user).

### 5. Test connection (P0)

Config completeness check → `config_ok | config_incomplete | stub_not_live`.  
No fake “connected” green on stubs. Live ping: P1+ (1С OData / open-bank HTTP); VTB after ИБК+cert.

### 6. Secrets / tenancy

Unchanged from ADR-022: Crypt at rest; Inertia only `has_secrets` / `secret_keys`; jobs fail-closed with `TenantContext`; audit without secret values.

## Consequences

### Positive

- Tenant admins can start 1С/bank onboarding without Ops for catalog adapters.  
- Open-doc banks (T-Bank, Точка, Сбер, Альфа) are discoverable; VTB stays honest about ИБК.  
- AA deal packaging stays platform-managed.

### Negative / debt

- Stubs without runtime may create support questions — mitigate with clear `runtime_status` + Test statuses.  
- Sber/Alfa OAuth product onboarding still bank-side work before live ping.

### Mitigations

- Registry `assertTenantBindable`; tests for non-self-serve 4xx.  
- SSRF guard on tenant-supplied URLs (esp. 1С `base_url`).  
- Badge «документация» when `docs_open`.

## PO answers (2026-11-23)

| # | Topic | Decision |
| --- | --- | --- |
| 1 | VTB | Stub + checklist; live after account+ИБК |
| 2 | 1С standard vs custom | Product = standard single-pub; AA multi-pub Ops-only; per-tenant overlay OK |
| 3 | Who manages | Role `admin` |
| 4 | Test button | Config check / honest stub (architect+ECC) |
| 5 | No feature | Lock + upsell, do not hide |
| 6 | Extra banks | Add T-Bank, Точка, Сбер, Альфа (public docs); skip obscure |

## Implementation notes

- Config: `config/saas-connectors.php`  
- Registry: `IntegrationConnectorRegistry::catalogForTenant()`, `assertTenantBindable()`  
- UI/routes: follow ADR-022 service (`TenantConnectorService`); new Settings controller — separate delivery  
