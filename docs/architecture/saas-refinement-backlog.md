# План доработок Traklo Pro SaaS (product backlog)

> **Дата:** 2026-10-15  
> **Статус:** согласовано; **частично реализовано** (WS-1 quarantine, WS-2 aliases UI, WS-3 print gate, WS-4 hide 1C UI)  
> **Не путать со staging (ADR-014)** — деплой: `docs/sync/server-deploy-guide.md`

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

Порядок: WS-1 + WS-3 → WS-2 → WS-4 → WS-5 → WS-7 → WS-8. **WS-6 пропущен.**

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

### WS-1 — судьба кода 1С/ЭПД

| Оставить | Убрать / карантин |
| --- | --- |
| MCP + docs; хуки `tenant.settings.integrations[]` | Product-routes Astral / 1C-Fresh, токены, webhooks как «фича тарифа» |
| Место под Enterprise-адаптер в этом репо | Перенос AA `OneCBpClient` / sandbox 1:1 |

При Enterprise-сделке — **новый** адаптер под 1С клиента, не revive AA Fresh.

---

## Explicit non-goals

- 1С / Astral ЭПД / Load Board / intercompany как фичи Start–Pro  
- Паритет с внутренней CRM АА  
- Seed DOCX  
- Биллинг/PSP на этапе бесплатных пилотов  
- Custom domain всем; Traklo mobile в base  
- Staging topology (ADR-014) — другой трек  
- Отдельный published package «адаптеров АА»

---

## Предлагаемые ADR (когда скажете писать)

1. **ADR-015** — Integrations: MCP-first + Enterprise custom adapters (in-repo)  
2. **ADR-016** — Print placeholder dictionary (platform catalog + tenant aliases from allowlist; no DOCX seed)  
3. **ADR-017** (опционально) — AA vertical quarantine / remove accounting-sync UX until Enterprise  

---

## Связь со staging

Артефакты ADR-014 / `.env.staging.example` / `deploy-staging.sh` остаются валидны. Этот backlog — **продуктовая** готовность пилотов, не замена host/DNS/TLS.
