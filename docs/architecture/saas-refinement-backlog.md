# План доработок Traklo Pro SaaS (product backlog)

> **Дата:** 2026-11-08 (обновлено: WS-9 AI-секретарь)  
> **Статус:** согласовано; **частично реализовано** (WS-1…WS-4, UX); WS-5/7/8 и **WS-9** — в плане  
> **Не путать со staging (ADR-014)** — деплой: `docs/sync/server-deploy-guide.md`  
> **Источники WS-9:** мнения saas-architect / privacy / security / qa (2026-11-04)

---

## Принцип: SaaS ≠ Автоальянс

| Слой | Правило |
| --- | --- |
| Универсальное ядро | Лиды → заказы → контрагенты → задачи → RBAC → (Pro) оплаты / печать / почта |
| Настройки тенанта | Юрлица, нумерация, DOCX, скрипты, брендинг — данные, не форк |
| Карантин АА | 1С Fresh, Astral ЭПД, биржа, intercompany, полный АА-документооборот — не в Start/Pro |
| Интеграции | Self-serve = MCP + документация; **custom-адаптер = Enterprise**, код в **этом** репо под сделку |
| Печать | Платформенный справочник + расширения тенанта (вариант B); DOCX **не сидим** |

---

## Зафиксированные решения

| # | Решение |
| --- | --- |
| 1 | Enterprise custom-адаптер разрешён; АА-1С как продукт не поставляем |
| 2 | Плейсхолдеры: **вариант B** — встроенное сопоставление + свои макросы, путь выбирается из allowlist-списка каталога |
| 3 | Текущую АА-1С **не переносить 1:1**. Даже тенант «своя компания» получит другую 1С → адаптер пишется заново; паттерны — из git/v5 при сделке. В репо SaaS: **strip/quarantine** существующих Astral/1C-Fresh stubs (рекомендация архитектора: A) |
| 4 | Новым тенантам **не сидим DOCX** — загружают сами |
| 5 | **Биллинг не трогаем** — пилоты бесплатные |
| 6 | UI «введено в 1С» и аналогичный АА-учётный UX — **убрать до Enterprise** |
| 7 | Enterprise-адаптеры встраиваем **в этом репозитории** (не отдельный private-репо) |

### Печать — вариант B (детализация)

1. **Платформенный каталог** — канонические пути (`order.number`, `order.client.name`, …) и короткие имена по умолчанию.
2. **Встроенное сопоставление** — шаблон тенанта мапит макросы DOCX → пути каталога.
3. **Свои плейсхолдеры тенанта** — админ добавляет макрос и выбирает сопоставление **только из списка** allowlisted путей каталога (не произвольная строка `order.foo.bar`).
4. Шаблоны DOCX — upload; seed файлов нет.

---

## Workstreams

Порядок: WS-1 + WS-3 → WS-2 → WS-4 → WS-5 → WS-7 → **WS-9 Phase 1** → WS-8. **WS-6 пропущен.**

| WS | Фокус | Prio | Суть |
| --- | --- | --- | --- |
| **WS-1** | Интеграции | P0 | MCP+доки; контракт Enterprise-адаптера в репо; strip АА 1C/EPD product-surface |
| **WS-2** | Справочник печати | P0 | Каталог + UI маппинга; tenant aliases из allowlist; без seed DOCX |
| **WS-3** | Feature gating | P0 | `feature:*` на routes/меню/MCP; Start ≠ Pro |
| **WS-4** | Карантин АА | P0 | Убрать «введено в 1С»; Load Board / intercompany / АА-копирайт вне Start–Pro |
| **WS-5** | Брендинг + онбординг | P1 | Тенант = их компания; ≤30 мин до заказа; пустая папка шаблонов |
| **WS-6** | Биллинг | — | **Deferred** до первых платных |
| **WS-7** | Doc checklist | P1 | Простой Pro-чеклист (метаданные), без файлов DOCX |
| **WS-8** | Точечные порты v5 | P2 | Exact installment amounts, unload locks, CMR FE — после печати/gating |
| **WS-9** | AI-секретарь на дашборде | P1 | Brief + Agenda + Action Cards поверх детерминированных сигналов (не KPI-чат) |

### WS-1 — судьба кода 1С/ЭПД

| Оставить | Убрать / карантин |
| --- | --- |
| MCP + docs; хуки `tenant.settings.integrations[]` | Product-routes Astral / 1C-Fresh, токены, webhooks как «фича тарифа» |
| Место под Enterprise-адаптер в этом репо | Перенос AA `OneCBpClient` / sandbox 1:1 |

При Enterprise-сделке — **новый** адаптер под 1С клиента, не revive AA Fresh.

---

### WS-9 — AI-секретарь на дашборде («секретарь-всезнайка»)

**Статус:** planned · **Prio:** P1 · **Зависимости:** живой дашборд + задачи + (Pro) почта/заказы; ADR-011  
**Принцип команды:** советник с правами пользователя, не автопилот. **read → propose → human confirm**.

#### Продуктовая форма

| Артефакт | Назначение |
| --- | --- |
| **Daily Brief** | 2–5 предложений «как идут дела» (LLM narrative поверх фактов) |
| **Agenda** | 3–7 пунктов сегодня / просрочено |
| **Action Cards** | primary UI: одна кнопка → задача / заказ / письмо / follow-up draft |
| KPI chips | остаются шапкой **фактов**, не «инсайтами» |

Цикл пользователя: задачи → коммуникации/почта → заказы (и сущности роли) → секретарь анализирует и предлагает следующее действие.

#### Data plane (до LLM)

Детерминированный `WorkPulseAggregator` (tenant + user scope):

- overdue / due-today tasks (линк lead/order);
- money-to-collect / stalled receivables;
- silent clients (нет mail/comms N дней при открытом ордере);
- orphan tasks / replies without next step;
- role workload (мои vs делегированные).

LLM получает **только сжатый snapshot**; цифры, списки, deep-links — из агрегатора, не «из головы» модели.

#### Нейминг (код)

| Компонент | Роль |
| --- | --- |
| `WorkPulseAggregator` | факты / сигналы |
| `SecretaryBriefBuilder` | prompt + narrative |
| `ActionCardResolver` | deep-links / suggested next |
| `SecretaryPanel` | UI на dashboard |

#### Фазы

| Фаза | Содержание | DoD |
| --- | --- | --- |
| **9.1** | Pulse + Agenda + 3–5 Action Cards; **без чата** | AC ниже; empty-state без галлюцинаций; AI down → карточки живы, narrative скрыт |
| **9.2** | Персонализация роли + связка с AI coaching analytics | Role-fit golden scenarios |
| **9.3** | Write-back: создать задачу / черновик письма — **только draft + confirm** | Whitelist tools; повторная AuthZ; audit confirm/reject |

#### Privacy / security gates (встроить в 9.1)

- Context pack: сводки и псевдонимы, **не** сырые письма/ПДн/маржа; маски телефонов/email/сумм; без вложений.
- Отдельный **opt-in**; экран «что уйдёт в AI»; toggle off + очистка очереди.
- Retention брифов 7–30 дн (дефолт 14); audit без сырых тел.
- AuthZ = те же гейтвеи, что UI (`RoleAccess`, order/mail authorization).
- Почта = недоверенный ввод (prompt injection); system policy неизменяема.
- Per-user / per-tenant rate + token caps.

#### QA / пилот

- 8–12 golden-сценариев (actionable / grounded / role-fit / non-duplicate).
- Failure modes: пустые данные, stale mail, чужая роль, ложная срочность, дубли.
- UX: snooze / dismiss / kill-switch до GA; один сильный совет лучше пяти слабых.
- Метрики: клик/выполнение, % «неактуально», тишина когда спокойно, NPS пилота.
- Пилот: 5–7 менеджеров, 2 недели; стоп при >30% dismiss или ролевой утечке.

#### AC Phase 9.1

1. Brief/карточки user X — только его tenant + его visibility scope (изоляция в тесте).  
2. Каждая Action Card → существующая сущность; overdue/due совпадает с KPI chips (±0).  
3. Нет сигналов → честный empty-state без LLM; AI недоступен → факты/карточки без narrative.

#### Non-goals WS-9 (сейчас)

- Чат-секретарь / агент с авто-отправкой писем или сменой статусов заказа  
- Cross-tenant бенчмарки / shared embeddings  
- Vector-RAG по всей почте тенанта  
- Real-time «AI always on» push  
- Кастомные system-prompts тенанта без ревью  
- Автономные side-effects без confirm  

---

## Explicit non-goals

- 1С / Astral ЭПД / Load Board / intercompany как фичи Start–Pro  
- Паритет с внутренней CRM АА  
- Seed DOCX  
- Биллинг/PSP на этапе бесплатных пилотов  
- Custom domain всем; Traklo mobile в base  
- Staging topology (ADR-014) — другой трек  
- Отдельный published package «адаптеров АА»  
- AI-секретарь как автопилот (см. WS-9 non-goals)

---

## Предлагаемые ADR (когда скажете писать)

1. **ADR-015** — Integrations: MCP-first + Enterprise custom adapters (in-repo) — *done*  
2. **ADR-016** — Print placeholder dictionary — *done*  
3. **ADR-017** (опционально) — AA vertical quarantine / remove accounting-sync UX until Enterprise  
4. **ADR-018** — Dashboard AI Secretary: pulse facts + brief + action cards; no autonomous side-effects; privacy pack rules (ссылка на ADR-011)

---

## Связь со staging

Артефакты ADR-014 / `.env.staging.example` / `deploy-staging.sh` остаются валидны. Этот backlog — **продуктовая** готовность пилотов, не замена host/DNS/TLS.
