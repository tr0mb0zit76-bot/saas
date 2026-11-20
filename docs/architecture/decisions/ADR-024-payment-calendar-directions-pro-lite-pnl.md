# ADR-024: Платёжный календарь (UX), направления, Pro-lite УУ, управленческий ОПиУ

**Status:** Accepted  
**Date:** 2026-11-20  
**Related:** ADR-020 (budgeting unit-economics), ADR-023 (workbook + thin cash), ADR-022 (connectors), ADR-003 / ADR-006 (packaging / gating), ADR-015 (integrations)  
**Amends:** ADR-023 phased delivery (P1/P2 scope); soft-amends ADR-003 («мелочи» УУ в Pro — см. §4)  
**Does not replace:** ADR-020 math; ADR-022 connector model  
**Team input:** saas-architect · CFO brief (cash vs accrual honesty) · PO lock 2026-11-20 · **CFO re-check 2026-11-20** (три линзы; IA = усилить «График оплат»)  
**Reference UX (organization / control pattern — not pixel/SKU clone):** Fintablo plan-execution dashboard (gauges, plan|факт|%, by directions, incomes/expenses/total)

## Context

После разбора Финтабло PO зафиксировал:

1. Цель = **паттерн контроля**, не parity модулей.  
2. Нужен **полный платёжный календарь** UI «примерно как скрин»: точность планирования, gauges выполнения, иерархия plan|факт|%, разрез по направлениям, тогглы поступления/списания/итого — на **вертикальных** данных (заказы + графики оплат + касса), не generic FinPlan.  
3. **Направления** (юрлицо / тип перевозки / …) — **да, P1**.  
4. Банк: **гибкий ingest** — у кого-то bank API/клиент, у кого-то файл + 1С; в рамках ADR-022 allowlist adapters per tenant.  
5. Часть **лёгких** УУ-мелочей можно в **Pro**, не только Enterprise — список жёстко ограничен.  
6. Вопрос: можно ли **ОПиУ** с селектором периода M / Q / H / Y?

ADR-023 P0 (workbook + thin cash + soft-map) остаётся каноном оболочки; этот ADR расширяет P1/P2 и честно отвечает по ОПиУ.

## Decision

### 1. Locked PO decisions (2026-11-20)

| # | Решение |
| --- | --- |
| 1 | Competitive goal = **control pattern**, не module parity с Финтабло. |
| 2 | Целевой UX (скрин) — plan vs fact, accuracy %, gauges, directions; данные = orders/schedules (+ bank fact в Enterprise). |
| 3 | **Направления** — P1 (минимум: юрлицо; далее тип перевозки / tenant-defined). |
| 4 | Bank ingest = **гибкий**: file / bank API / 1С — ADR-022 allowlist. |
| 5 | Pro: **enhance График оплат** (не отдельный «calendar lite»); полный УУ / budgeting / bank join — Enterprise. |
| 6 | **ОПиУ** — упр. маржинальный отчёт M/Q/H/Y; **не** бух. ОПиУ (см. §5). |
| 7 | **Три линзы** (§2a): касса «сейчас» ≠ ОПиУ ≠ ДЗ/КЗ overlay; один глобальный cash↔accrual — запрещён. |

### 2. Платёжный календарь — UX target (не клон Финтабло)

**Job:** CFO/финдиректор видит *сейчас*, выполняется ли план денег (и где отставание), без ожидания конца месяца.

| Элемент UX (скрин) | Источник плана | Источник факта | Контур |
| --- | --- | --- | --- |
| Точность планирования (bars + % line) | График оплат (ожидаемые даты/суммы) ± бюджет кассы | Выписка / ручной cash + отмеченные оплаты schedule | Касса |
| Gauges поступления / списания | То же | То же | Касса |
| Таблица plan \| факт \| % выполнения | Статьи / иерархия | Разнесённые линии + schedule settlement | Касса |
| По направлениям | Атрибут заказа / schedule → direction | Агрегат факта с тем же ключом | Касса |
| Тогглы Поступления / Списания / Итого | Фильтр знака потока | Фильтр знака потока | Касса |

**Не смешивать** в этом экране математику Budgeting (margin − OPEX / hiring). Календарь = **деньги**; Budgeting = **план маржи**. Честные лейблы обязательны (канон ADR-020/023).

**IA (CFO re-check 2026-11-20):** **не** новый пункт меню и **не** бренд «calendar lite». Усилить существующий Pro-экран **`/finance?section=cashflow` («График оплат»)** — dashboard plan|факт|gauges|directions **над** уже существующим `CashFlowGrid`. Enterprise добавляет join банковского факта + thin cash «сейчас» (см. §4, §8).

### 2a. Три линзы денег (не один переключатель «касса↔начисление») — CFO lock

PO хочет и cash, и accrual, и «полную картину с ДЗ/КЗ». Это **три разных вопроса**, не два режима учёта:

| Линза | Вопрос CFO | Данные | Где в продукте |
| --- | --- | --- | --- |
| **A. Кассовая позиция («сейчас»)** | Сколько денег на счетах/в кассе **прямо сейчас**? | Opening + обороты выписки / ручной ввод (ADR-023 thin cash) | УУ → Касса; Enterprise |
| **B. Управленческий ОПиУ (начисление)** | Заработали ли за период по **признанию** заказов? | Выручка/прямые/маржа по дате признания заказа (§5) | Упр. ОПиУ; Enterprise |
| **C. Оборотный капитал / ДЗ+КЗ overlay** | Что ещё **ожидается** к получению/оплате (графики + неоплаченное)? | Unpaid / pending `payment_schedules` (+ опц. счета) | **График оплат** dashboard; Pro уже имеет сырьё |

**CFO verdict:**

1. **«Сейчас» в УУ = только линза A (cash).** Не подмешивать туда ДЗ/КЗ в одну цифру без явной второй строки.  
2. **«Полная картина» PO** = A (факт кассы) **+** C (ожидаемые притоки/оттоки) = *проекция ликвидности*, **не** переключение метода учёта на accrual.  
3. **On-the-fly «cash ↔ accrual» одним тумблером на всём УУ — нет.** Это смешивает остаток банка с прибылью периода и врёт лейблами.  
4. **Честные переключатели (два независимых контроля):**  
   - На **отчётах прибыли/маржи:** контур `По деньгам` | `По заказам (начисление)` — только где оба имеют смысл (ОПиУ / маржа), с разными заголовками.  
   - На **деньгах / Графике оплат:** оверлей `Только факт` | `Факт + ожидания (ДЗ/КЗ)` — линза A vs A+C; **не** называть это «начисление».  
5. Опционально третья строка KPI: «Касса сейчас / +ожид. поступления / −ожид. оплаты / = проекция» — три числа, не одно.

### 3. Направления (P1)

- **Direction** = tenant-scoped аналитический разрез для плана/факта/календаря/ОПиУ.  
- **P1 minimum:** `own_company_id` (юрлицо на заказе) как первое направление.  
- **P1+:** тип перевозки / иные ключи из заказа (если поле стабильно); опционально tenant handbook «Направления» с soft-map заказ→направление.  
- Все агрегаты календаря и ОПиУ принимают `direction_id` / ключ в фильтрах.  
- Без direction не блокируем P0 thin cash; без direction **не** считаем «готовый» calendar drill-down (как soft-map для variance).

### 4. Pro vs Enterprise — revised (amends ADR-003; drop «calendar lite»)

Имя **«calendar lite» упразднено** — это апгрейд того же **График оплат**, не второй модуль.

| Возможность | Pro (`payment_schedules`) | Enterprise |
| --- | --- | --- |
| Реестр / грид графиков (`CashFlowGrid`, оплата строк) | ✅ уже есть | ✅ |
| **Dashboard на Графике оплат:** gauges, plan\|факт\|accuracy %, directions, поступления/списания/итого; план и «факт» из **статусов schedule** (ожидание vs оплачено) | ✅ P1 enhance | ✅ |
| Overlay ДЗ/КЗ (линза C): ожидаемые in/out рядом с планом | ✅ на schedules | ✅ |
| Thin cash «сейчас» (линза A) + импорт/разнос выписки + soft-map | ❌ | ✅ `management_accounting` |
| Join **банковского факта** в тот же dashboard (plan schedule ↔ выписка) | ❌ | ✅ P2 |
| Бюджетирование / CVP / hiring | ❌ | ✅ `budgeting` |
| Упр. ОПиУ (линза B) + toggle деньги\|начисление | ❌ | ✅ |
| Bank/1С/API connectors | ❌* | ✅ `integrations` + ADR-022 |

\*Add-on billing — только отдельным ADR-009; settings не повышают тариф.

**Принцип Pro:** усиливаем `/finance?section=cashflow`; **ноль** statement reconcile / дерева УУ / freeze бюджета в Pro.

### 5. ОПиУ — вердикт (Q6) + CFO honesty

#### Feasibility

| Вопрос | Ответ |
| --- | --- |
| Можно ли сделать отчёт с селектором **месяц / квартал / полугодие / год**? | **Да** технически: rollup по `period_start`/`period_end` от выбранной даты признания. |
| Хватает ли данных заказа/маржи/затрат? | **Да для управленческого маржинального ОПиУ:** ставка клиента, затраты перевозчиков/компенсации, маржа заказа; OPEX из УУ-категорий — если Enterprise и есть факты. |
| Можно ли честно назвать это «ОПиУ как в 1С / Финтабло ОПиУ»? | **Нет.** Нет полного начисления, НДС-контура, амортизации, закрытия периода бухучёта, обязательств как в бух. модели. |

#### Accrual vs cash (CFO)

- **Касса / ДДС** = по дате денег (выписка, оплата schedule) — уже thin cash + календарь.  
- **Управленческий ОПиУ** = по **дате признания заказа** (PO-lock: одна база на тенанта, default = дата исполнения / закрытия заказа — уточнить при реализации, не «дата создания» молча). Выручка и прямые затраты рейса в том же периоде → маржа.  
- **Нельзя** подписывать cash-отчёт как «ОПиУ» и наоборот. UI: переключатель контура **«По заказам (начисление)»** vs **«По деньгам»** только если оба доступны; иначе одно имя + дисклеймер.  
- Рекомендуемое продуктовое имя: **«Управленческий ОПиУ»** или **«Маржа по периоду»** + подзаголовок «не бухгалтерский отчёт».

#### Phase

| Scope | Phase |
| --- | --- |
| Period selector M/Q/H/Y на **уже существующих** plan-fact / margin pivots (без полного P&L дерева) | **P1 light** |
| Полноценный лист «Управленческий ОПиУ»: выручка − прямые − (опц.) OPEX УУ; directions; export | **P2** |
| Бух. ОПиУ / баланс / налоги / амортизация | **Out** (1С) |

### 6. Банк / ingest (align ADR-022)

Не новый механизм — **расширение allowlist adapters**:

| `adapter_key` (примеры) | Роль |
| --- | --- |
| `statement_file_xlsx` | Файловая выписка (уже путь УУ) |
| `one_c_bp` | Операции/остатки из 1С (шаблон AA / clone) |
| `bank_api_*` | Per-bank или агрегирующий клиент — только in-repo class + platform bind |

- Tenant без `feature:integrations` может иметь **только** file ingest внутри `management_accounting` (как сейчас).  
- API/1С — connector instance + Enterprise `integrations` (канон ADR-022).  
- Jobs всегда с `tenant_id` + TenantContext; секреты не в Inertia.

### 7. QA / isolation gates (`tests/Feature/Saas`)

- Calendar aggregates: tenant A never sees B schedules/statements.  
- Direction filters: same-tenant FKs only.  
- Pro cannot call Enterprise UA mutations (import, category write, budgeting).  
- P&L period rollup: fixture asserts M vs Q totals consistency; label/contour flag present in props.  
- Connector bank adapters: wrong tenant / feature off → 404/403.

## Consequences

### Positive

- Понятный UX-ориентир без гонки за Финтабло SKU.  
- Pro получает ценность на уже оплаченных schedules.  
- ОПиУ назван честно → меньше разочарований CFO.  
- Bank flexibility без ломки ADR-022.

### Negative

- P1/P2 тяжелее исходного ADR-023.  
- Риск смешать «календарь lite» (Pro) и «полный» (Enterprise) в одной UI без feature cuts.  
- Дата признания выручки — политический выбор; ошибка default = кривой ОПиУ.

### Mitigations

- Props `cashflow_dashboard_mode: schedules_only | schedules_plus_bank` (не отдельное меню).  
- Два независимых UI-контроля (§2a), никогда один глобальный «метод учёта».  
- Дисклеймер ОПиУ + tenant setting `pnl_recognition_basis`.  
- Screenshot = reference composition, не pixel parity.

## Alternatives considered

1. **Клон Финтабло FinPlan** — отклонено PO (control pattern only).  
2. **Отдельный пункт «Календарь» / бренд calendar lite** — отклонено CFO/architect 2026-11-20 (дубль Графика оплат).  
3. **Один тумблер cash↔accrual на всём УУ** — отклонено CFO (три линзы; риск лейблов).  
4. **Называть cash-отчёт ОПиУ** — отклонено CFO.  
5. **Полный бух. ОПиУ в продукте** — отклонено (1С).  
6. **Отдельный billing SKU на каждый банк** — отклонено (ADR-022).

## Phased delivery (rephased vs ADR-023)

| Phase | Name | Deliverables | Effort |
| --- | --- | --- | --- |
| **P0** | Книга + thin cash | Без изменений ADR-023 P0 | — |
| **P1** | Directions + enhance График оплат + period light | Direction (юрлицо+); dashboard gauges/plan\|факт/accuracy на `/finance?section=cashflow`; overlay ДЗ/КЗ; Enterprise: ICP OPEX / +N / monthly grid; period M/Q/H/Y light | ~3–4 weeks |
| **P2** | Bank join + упр. ОПиУ | Schedules + bank fact на том же экране; упр. ОПиУ + toggle деньги\|начисление; bank adapters; sensitivity | ~4–5 weeks |

## Open questions

Не блокируют P1:

1. **База признания** упр. ОПиУ: исполнение / закрытие / погрузка? (default: исполнение/закрытие.)
