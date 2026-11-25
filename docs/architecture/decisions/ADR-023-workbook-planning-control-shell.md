# ADR-023: Workbook-like оболочка планирования и контроля (УУ + Бюджетирование)

**Status:** Accepted  
**Date:** 2026-11-18  
**Related:** ADR-020 (Accepted — unit-economics budgeting), ADR-003 / ADR-006 (Enterprise features), ADR-002 / ADR-010 (tenant isolation)  
**Amended by:** [ADR-024](ADR-024-payment-calendar-directions-pro-lite-pnl.md) (2026-11-20) — платёжный календарь UX, направления, Pro-lite, упр. ОПиУ; P1/P2 ниже superseded для scope  
**Does not replace:** ADR-021 (content packs), ADR-022 (connectors)  
**Team input:** saas-architect · CFO (×2) · saas-qa · saas-privacy · saas-security (2026-11-18)  
**PO decisions:** 2026-11-18 (имя, plan-fact, soft-map, ДДС до внешнего Enterprise, KPI UX, модель УУ)  
**Reference Excel (organization / simplicity — not freight content, not numeric parity):** `Управленка.xlsx`, БП «микроветы».xlsm, `точка безубыточности-jr.xls`, `бизнес-эскиз.xlsx`

## Context

ADR-020 зафиксировал продуктовую рамку бюджетирования: **план выхода в ноль / калькулятор найма**, P0 `per_head` + взносы, soft plan vs actual с УУ. Математика Budgeting = margin − OPEX — **не касса**.

PO хочет Excel-подобную **организацию и удобство** (простота, информативность, гибкость: свои статьи, фильтры, графики под эту модель) — не совпадение цифр с файлами и не порт БП.xlsm.

Паттерн **Управленка.xlsx**: выписка кормит факт по безналичным оборотам; накопительное **сальдо**; ручные статьи рядом. «Безналичные статьи» в продукте = статьи, факт по которым берётся из **банковской выписки** (безналичные расчёты), **не** «неденежные» / non-monetary.

## Decision

### 1. Locked PO decisions (2026-11-18)

| # | Решение |
| --- | --- |
| 1 | Имя модуля плана: **«Бюджетирование»** (подзаголовок/дисклеймер ADR-020 сохраняем). |
| 2 | ICP OPEX-шаблон: **минимальный seed в P0** (дешёвый starter на enable Enterprise); полный ICP-каталог — **early P1**. |
| 3 | Plan-fact: **live draft допустим** с явным предупреждением; **freeze** = «официальные» отклонения. |
| 4 | Soft-map статья бюджета → категория УУ: **обязателен для пилота** (FK на OPEX, иначе variance не считается «готовым»). Ввод сумм бюджета по-прежнему не требует проводок УУ (канон ADR-020). |
| 5 | **Тонкий лист ДДС / касса — до первого внешнего Enterprise** (раньше бывшего «только P2»). Полный DSO/DPO с графиков оплат — позже. |
| 6 | KPI успеха: **простота / информативность / гибкость** (добавление строк статей, фильтры аналитики, графики под модель) — **не** numeric match Excel. |

### 2. Workbook shell (не клон Excel)

Семантические листы (tabs), typed forms, не свободная сетка формул.

| Лист | Хост | Job |
| --- | --- | --- |
| **Драйверы** | Budgeting | N, маржа/голову, % взносов, горизонт |
| **План OPEX** | Budgeting | lump / per_head / % маржи + ramp; add-row; soft-map → УУ |
| **Timeline / вехи** | Budgeting | ноль месяца, cumulative, пик финансирования (не «касса») |
| **CVP** | Budgeting | пост./перем. из typed OPEX; не обещать кассу |
| **Статьи** | UA | дерево; лёгкое добавление / фильтр |
| **План / факт** | UA | статья × период: план \| факт \| Δ; live+warning или freeze |
| **Касса (тонкая ДДС)** | UA | «сколько денег сейчас» + обороты; см. §4 |

IA: разные routes/feature flags, общее **оглавление книги** (TOC-ссылки).

### 3. Модель УУ (операционный контур)

1. **Безналичный факт** — авто из импорта/разноса банковской выписки на статьи (cashless settlement lines).  
2. **Прочее** — ручной ввод (то, чего нет в банке).  
3. **Сейчас** — реальное сальдо: opening (из выписки / ручной старт) + накопительные приход/расход (паттерн Управленка).  
4. **Ретро** — pivot / план-факт за период.  
5. **Прогноз** — лёгкий predictive (известные будущие движения / план); не полный schedule-DSO в пилоте.

Подписи: остаток = «по выписке / ручному вводу», не «полный ДДС компании» без оговорок.

### 4. Касса раньше: CFO lock ([CFO re-check](b6fea1d0-bbab-41ea-99ec-c9d6ce2cd471))

- **Пилотная касса** = statement-anchored balance + безнал-факт + manual other — **да, до внешнего Enterprise**.  
- **Не** подключать payment-schedule DSO/DPO в пилотную «кассу» (это отдельный этап).  
- Budgeting / CVP / hiring «+N» остаются **«план маржи»**, никогда не переименовывать в кассу.  
- Риск тонкой кассы: CFO примет её за полный кошелёк → только честные лейблы + opening balance.

### 5. Канон ADR-020 (без пересмотра математики)

- Budgeting = unit-economics / exit-to-zero.  
- Soft plan vs actual; soft-map обязателен для *качества* pilot variance, не для ввода сумм.  
- Role buckets; без named salary / `user_id` на OPEX.  
- Privacy/security/QA gates из первого раунда ADR-023 сохраняются; soft-map `exists` **same-tenant**.

### 6. UX flexibility (KPI)

- Добавление строк статей расходов/доходов без «пересборки книги».  
- Фильтры для быстрой настройки аналитики.  
- Графики, привязанные к выбранным статьям/периоду/контуру (операционный / управленческий / касса), не декоративный дашборд.

### 7. QA gate (must-red in `tests/Feature/Saas`)

- Sheet/workbook props; plan-fact live vs freeze source flag.  
- Soft-map required validation на pilot path.  
- Thin cash: tenant-scoped balance props; no cross-tenant statement bleed.  
- Feature gates на новые мутации.

## Consequences

### Positive

- Excel-организация + понятная касса «как Управленка» до внешнего пилота.  
- Честное разделение план маржи (Budgeting) vs касса (UA).  
- Soft-map обязателен → plan-fact полезен на пилоте.

### Negative

- P0 тяжелее (касса + soft-map + seed).  
- Риск принять thin cash за полный ДДС.

### Mitigations

- Лейблы + opening balance; DSO/DPO явно следующим этапом.  
- Минимальный OPEX seed в P0, полный каталог early P1.

## Alternatives considered

1. **ДДС только P2** — отклонено PO (нужно до внешнего Enterprise).  
2. **Касса = margin−OPEX** — отклонено CFO.  
3. **Касса = auto DSO/DPO schedules в пилоте** — отклонено (объём/доверие); позже.  
4. **KPI = совпадение с Excel** — отклонено PO.  
5. **Жёсткая привязка ввода бюджета к проводкам УУ** — по-прежнему отклонено (ADR-020); обязателен только soft-map FK.

## Phased delivery (rephased)

| Phase | Name | Deliverables | Effort |
| --- | --- | --- | --- |
| **P0** | Книга + контроль + тонкая касса | TOC sheets; honest milestones; plan\|факт\|Δ (live+warning, freeze official); **soft-map required**; min ICP OPEX seed; add-article + basic filters; charts wired to article/period model; **thin cash sheet** (выписка→безнал факт, manual, cumulative «сейчас»); Feature/Saas gates | ~2–3 weeks |
| **P1+** | См. ADR-024 | Directions; Pro calendar lite; ICP catalog / +N / monthly grid; period M/Q/H/Y light | ~3–4 weeks |
| **P2+** | См. ADR-024 | Full payment calendar (schedules + cash); упр. ОПиУ; bank adapters; sensitivity | ~4–5 weeks |

P0 scope выше **не меняется**. Детали P1/P2 и Pro-lite / ОПиУ — только в ADR-024.

## Open questions

Opening balance: **оба** (выписка + ручной override) — зафиксировано в thin cash P0.  
Новые PO-вопросы 2026-11-20 закрыты в ADR-024 (остались неблокирующие: recognition basis, IA меню Pro lite).
