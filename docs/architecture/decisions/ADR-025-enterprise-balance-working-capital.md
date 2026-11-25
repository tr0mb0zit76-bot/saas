# ADR-025: Enterprise «Баланс» + «Оборотный капитал» (WC-lite, не бух. баланс)

**Status:** Accepted  
**Date:** 2026-11-21  
**Related:** ADR-020 (budgeting), ADR-023 (workbook + thin cash), ADR-024 (cashflow lenses + упр. ОПиУ), ADR-022 (connectors), ADR-003 / ADR-006 (packaging / gating), ADR-011 (AI/LLM)  
**Amends:** ADR-024 §5 Out-list — уточняет: «полный бух./упр. баланс с двойной записью = Out» **не** запрещает модуль меню **«Баланс»** как честный WC/liquidity narrative; soft-amends ADR-024 §2a (линза C получает **отдельный** nav item, не только overlay в Графике оплат)  
**Does not replace:** ADR-024 three lenses; no second payment calendar; ADR-020 math  
**Team input:** saas-architect · prior Отчёты review [20a96975](20a96975-814c-4121-ae22-e4098d421aa6) · saas-qa · saas-privacy · saas-security (2026-11-21)  
**PO locks:** 2026-11-21 (модуль «Баланс» yes; отдельный AR/AP nav yes; Q3 recognition parked)  
**Reference (methodology / IA — not pixel/SKU clone):** Fintablo Отчёты «Баланс» + «Обязательства»; help `Управленческий Баланс`, `Отчет по обязательствам`; OpenAPI `FinTablo-v1-swagger.yaml`

## Context

После live-разбора Fintablo «Отчёты» архитектор рекомендовал **не** клонировать double-entry BS, а брать owner-questions (где заморожены деньги, свои/чужие, ликвидность) и AR/AP-реестр. PO ответил:

1. **Да — модуль «Баланс»** в меню (имя не выкидывать). Нужен честный Enterprise-контур, который **может вырасти**, но стартует как working-capital / liquidity / own-vs-borrowed narrative à la Fintablo — **без** заявления «это 1С бух. баланс», пока нет двойной записи.  
2. **Да — отдельный пункт меню** для AR/AP / обязательств / оборотного капитала (не только overlay внутри cashflow).  
3. Вопрос базы признания упр. ОПиУ (исполнение / закрытие / погрузка) **не понятен** PO → в ADR объяснить простым языком + рекомендовать default; **не блокировать** ADR.

Параллельно расширен scope: другие разделы меню Fintablo + help/API → borrow/adapt/skip для нашей Enterprise finance analytics.

Канон ADR-024 сохраняется: три линзы; **нет** второго платёжного календаря; полный 1С double-entry **Out**, пока PO явно не override.

## Decision

### 1. Locked PO decisions (2026-11-21)

| # | Решение |
| --- | --- |
| 1 | Меню **«Баланс»** — **да** (Enterprise). Содержание P0 = WC/liquidity / «свои vs чужие» narrative; **не** бух. баланс 1С. Подзаголовок/дисклеймер обязателен. |
| 2 | Отдельный nav для AR/AP-реестра — **да**. Канон. лейбл: **«Оборотный капитал»** (не «Баланс», не дубль имени sibling). Alt «Обязательства» допустим как синоним в help, не как primary nav. |
| 3 | База признания упр. ОПиУ — **parked** с recommended default (см. §6); ADR Accepted без PO confirm. |
| 4 | Полный double-entry BS / ОС / амортизация / уставный капитал balancing — **Out** до явного override PO. |
| 5 | Второй «Платёжный календарь» — **нет** (канон ADR-024). |

### 2. Что такое наш «Баланс» (honest growth path)

Fintablo help: управленческий баланс = активы↔пассивы + двойная запись + стартовый месяц + уставный капитал + ОС/склад/кредиты. **Мы это не копируем в P0.**

| Вопрос собственника (Fintablo) | Наш P0 источник | Лейбл |
| --- | --- | --- |
| Где «заморожены» деньги? | ДЗ заказчиков + КЗ перевозчиков из `payment_schedules` + касса (thin cash) | «В дебиторке / на счетах / к оплате» |
| Сколько своих vs чужих? | Soft: касса + ДЗ vs КЗ (+ опц. авансы); **без** equity/ОС | «Свои деньги (оценка)» / «Чужие (долги)» + disclaimer |
| Ликвидность | `(касса) / КЗ` и `(касса+ДЗ)/КЗ` как **управленческие** коэффициенты | «Ликвидность (упр.)» — не «коэф. из бух. баланса» |
| Период M/Q/H/Y | Snapshot на конец периода из тех же агрегатов | Как у ОПиУ period selector |

**Рост (без смены имени модуля):**

| Stage | Scope | Claim |
| --- | --- | --- |
| **P0** | WC balance **view**: касса + ДЗ + КЗ + 2–3 KPI + disclaimer | «Управленческий срез оборотного капитала» |
| **P1** | Richer KPIs, drill → «Оборотный капитал», направления, period rollup, Excel export | То же + drill |
| **P2** | Optional fuller BS **только** если PO overrides Out (события, equity stub, OS) | Тогда можно снять disclaimer «не бух.» **частично** — отдельный ADR |

### 3. «Оборотный капитал» = линза C как отдельный модуль

| | |
| --- | --- |
| **Job** | Кто/кому/сколько должен **сейчас** (заказчики ↔ перевозчики), с drill к заказу/строке графика |
| **Данные** | Unpaid/pending `payment_schedules` (+ contractor); позже — авансы, payroll/loans только отдельным scope |
| **Не путать** | Это **не** «начисление» и **не** «Баланс»; overlay «Факт + ожидания» на Графике оплат **остаётся** (ADR-024), но полный реестр — здесь |
| **Pro** | Сырьё уже в schedules; **отдельный nav** — Enterprise (`management_accounting`), Pro держит overlay на cashflow |
| **Query rule** | Один tenant-scoped query helper для UI + export + MCP (fail-closed) |

### 4. Menu IA (Enterprise finance)

```
Финансы
├── График оплат          → /finance?section=cashflow
│     lenses: Сейчас (A, Ent) | Факт+ожидания (C overlay) | Упр. ОПиУ stub (B)
├── Оборотный капитал     → /finance/working-capital   [NEW, Ent]
│     AR/AP register by contractor → drill order/schedule
├── Баланс                → /finance/balance           [NEW, Ent]
│     WC/liquidity narrative (P0) + disclaimer
├── Управленческий ОПиУ   → sheet / finance UA (P2 full; P1 light period)
├── УУ / Касса            → /finance/management-accounting (workbook)
└── Бюджетирование        → /budgeting
```

**Не добавлять:** «Платёжный календарь» как sibling; «Обязательства» как третье имя рядом с «Оборотный капитал».

### 5. Fintablo inventory → borrow / adapt / skip

#### 5a. Отчёты (prior + confirmed)

| Fintablo | Verdict | Our home |
| --- | --- | --- |
| Сводка | **Adapt** gauges/plan\|факт | Cashflow dashboard |
| Панель приборов | **Skip** NF clone; selective charts later | Workbook charts |
| Движение денег | **Adapt** | Thin cash + P2 bank join |
| Прибыли и убытки | **Adapt** упр. ОПиУ | ADR-024 B |
| Обязательства (отчёт) | **Borrow** → **Оборотный капитал** | §3 |
| Баланс | **Borrow narrative** → module **Баланс** WC-lite | §2 |
| ИИ-аналитик | **Skip** now | ADR-011 + privacy block |

#### 5b. Другие меню (live nav 2026-11-21 + help)

| Area | Items | Verdict | Note |
| --- | --- | --- | --- |
| **Деньги** | Операции, Импорт, Автоправила, Сверка | **Adapt** Импорт/разнос; **partial** автоправила later; сверка = bank join P2 | У нас: UA statement import (ADR-023/022), не generic DDS ledger UI |
| **Планирование** | Заявки на оплату, Платёжный календарь, Бюджет ДДС, Бюджет ДиР | **Skip** calendar SKU; **adapt** plan-fact pattern into Budgeting/UA | ADR-024: enhance График оплат only |
| **Зарплаты** | Ведомость, сводная, сотрудники | **Skip** MVP finance analytics | Out of 6‑мес finance; PD-heavy |
| **Сделки** | Учет, каталог, склад, рентабельность | **Adapt** рентабельность → order margin / упр. ОПиУ; **skip** склад | CRM Orders = vertical «сделки» |
| **Инструменты** | Обязательства, Кредиты, Налоги, Имущество | **Adapt** обязательства → WC register; **skip** кредиты/налоги/имущество P0–P1 | Feed Баланс later only if Out lifted |
| **Справочники** | Счета, статьи, направления, контрагенты | **Adapt** направления + статьи UA; контрагенты уже CRM | ADR-024 directions |
| **Интеграции** | Bank/1С/API | **Adapt** via ADR-022 | No Fintablo-style public report API required |
| **Настройки** | Закрытие периода | **Skip** бух. close; soft freeze = ADR-023 plan_official | |

#### 5c. Help / API capabilities (design inputs)

| Capability | Fintablo | Our design |
| --- | --- | --- |
| Import xlsx/csv + column map + duplicate check + autorules | Help import | Keep statement import; autorules = P2 nice-to-have |
| Export reports Excel (hierarchy / numeric) | Help export | P1+ for WC/Баланс/ОПиУ; tenant export tables before external pilot |
| Dimensions: articles, directions, accounts, partners | Справочники + API | Directions P1; articles = UA categories |
| API: CRUD transactions, obligations, deals, salary, assets, taxes, BDDS/BDR plan | OpenAPI tags; **no report endpoints**; **no webhooks** | Prefer compute reports in-app; connectors push ops (ADR-022); webhooks not required for MVP |
| Free-form ОПиУ lines (`pnl-item`) | API | Optional P2 for non-order OPEX fact — not P0 |
| Double-entry Balance events | Help Баланс | **Out** |

### 6. Q3 — база признания упр. ОПиУ (plain Russian + default)

**Зачем вопрос.** Управленческий ОПиУ отвечает: «Сколько **заработали** за месяц?», а не «Сколько **денег пришло**». Нужно выбрать **один** день на заказе, с которого выручка и прямые затраты попадают в период отчёта.

**Пример.** Заказ №100: клиент 100 000 ₽, перевозчик 70 000 ₽, маржа 30 000 ₽.

| Дата | Событие |
| --- | --- |
| 28 янв | Создали заказ в CRM |
| 5 фев | Погрузка |
| 12 фев | Рейс выполнен / статус «исполнен» |
| 20 фев | Закрыли заказ документами |
| 3 мар | Клиент оплатил по графику |

**Куда попадёт маржа 30 000 в упр. ОПиУ:**

| Вариант | Месяц в ОПиУ | Когда уместно |
| --- | --- | --- |
| По **погрузке** | февраль (5 фев) | Если считают «заработали, когда груз уехал» |
| По **исполнению** | февраль (12 фев) | Типично для экспедитора: услуга оказана |
| По **закрытию** | февраль (20 фев) | Когда важен полный пакет документов |
| По **оплате** | март | Это уже **касса**, не начисление — для ОПиУ **не** default |

Деньги по графику всегда живут в линзах A/C (График оплат / касса), независимо от выбора выше.

**Recommended default (parked):** `pnl_recognition_basis = execution_or_closed` — дата **исполнения**, если есть; иначе дата **закрытия**; **не** дата создания и **не** дата оплаты. Погрузка — опция тенанта позже, если PO подтвердит.

**PO action:** подтвердить позже; до confirm кодировать default выше + setting `pnl_recognition_basis`.

### 7. QA / security / privacy gates

**Team (2026-11-21):** [saas-qa](6ceb8100-7755-47ee-bdcb-e9dcf23467f3) · [saas-privacy](db48856e-fe82-46f4-99cb-91705ac3e16d) · [saas-security](c6f15683-8076-4f15-883e-d2eaf60cfefe) — staged ADR **safe to accept**; UI coding after P0 gates; external Enterprise pilot blocked until finance export/erase.

**P0 (до UI Баланс / Оборотный капитал — и до расширения dashboard):**

- `feature:payment_schedules` / Start не открывает cashflow dashboard Enterprise props (`?section=cashflow` must-red).  
- `feature:management_accounting` на `/finance/balance` (+ thin-cash KPI); Start/Pro → 403/404.  
- «Оборотный капитал» route: Enterprise + UA (меню ≠ доступ); Pro = overlay only.  
- Role gate: `canAccessManagementAccounting` на cash_now / Баланс; `canViewPaymentSchedules` (+ record scope) на реестр.  
- Isolation: tenant A ≠ B на schedules, expectations, WC aggregates.  
- Manager/`visibility` scope на AR/AP register.  
- Один scoped query для UI+export+MCP.  
- Props: `report_kind: working_capital_lite` + disclaimer (не `balance_sheet`).

**Must-red files (`tests/Feature/Saas/`):**

1. Extend `CashFlowControlDashboardTest` — Start `?section=cashflow`; role `cash_now`; expectations isolation; manager-scope.  
2. `WorkingCapitalBalanceTest.php` — feature + tenant + disclaimer «Баланс».  
3. `WorkingCapitalRegisterTest.php` — sibling route; AR/AP by contractor; UI≡export≡MCP scope.

**Privacy:**

- Contractor names + amounts = commercial PD surface; extend `TenantExportService` (+ statement files under `tenants/{id}/`) / erasure **before external Enterprise pilot**.  
- AI analyst over finance — **blocked** (opt-in + sanitizer + audit later, ADR-011).  
- Export title: «Управленческий срез / Оборотный капитал», не «Бухгалтерский баланс».

**Security:**

- No new unscoped raw SQL / `journal_*` for «баланс» aggregates — only scoped schedules + thin cash.  
- Contractor drill-down IDOR: `contractor_id` via same-tenant `Contractor` (+ visibility); foreign id → 404.  
- Double-entry event / equity / OS amortization tables — **Out** P0/P1.

### 8. Phased delivery

| Phase | Name | Deliverables | Effort |
| --- | --- | --- | --- |
| **P0** | Gates + IA stubs | Close prior cashflow isolation/feature gates; routes + empty/honest WC props for Баланс + Оборотный капитал OR ship minimal aggregates if gates green | ~1 w gates + ~1–2 w lite views |
| **P1** | Registers + KPIs | Counterparty AR/AP grid; Баланс KPI strip; period; export; directions filter | ~2–3 w |
| **P2** | Reports depth | Bank join (ADR-024); full упр. ОПиУ; optional autorules; **не** BS unless PO override | ~4–5 w (shared with ADR-024 P2) |

## Consequences

### Positive

- PO получает желанное имя «Баланс» без лжи про 1С.  
- Отдельный «Оборотный капитал» снимает перегрузку cashflow.  
- Рост-path до полного BS без переименования модуля.  
- Fintablo map закрывает «что ещё брать» вне Отчётов.

### Negative

- Риск, что пользователи ждут активы=пассивы с ОС/капиталом.  
- Два соседних пункта (Баланс / ОК) могут путать без onboarding copy.

### Mitigations

- Persistent disclaimer + `report_kind` in props.  
- Баланс P0 = summary; drill CTA → Оборотный капитал.  
- Help one-pager in tenant admin guide when shipping.

## Alternatives considered

1. **Не делать пункт «Баланс», только WC KPIs** — отклонено PO (имя нужно).  
2. **Клон Fintablo double-entry Баланс** — отклонено (Out / 1С; effort; data we don't have).  
3. **Назвать AR/AP тоже «Баланс» или только «Обязательства»** — отклонено (коллизия с sibling; «Оборотный капитал» яснее для ICP).  
4. **Держать AR/AP только overlay в cashflow** — отклонено PO (отдельный nav).  
5. **Блокировать ADR до confirm Q3** — отклонено (park + default).

## Open questions

Не блокируют Accepted:

1. PO confirm `pnl_recognition_basis` default (исполнение/закрытие vs погрузка) — см. §6.  
2. Нужен ли soft equity stub («вложения собственника») в Баланс P1 без double-entry? Default: **нет** до override.  
3. Pro: показывать ли read-only teaser «Оборотный капитал» или строго Enterprise-only? Default: **Enterprise-only** nav; Pro = overlay only.
