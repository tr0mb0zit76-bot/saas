# SaaS audit remediation — что правим при переезде

> Источник: `Exchange/CRM/v5-local/Components/Code Audit 2026-07.md`  
> Исполнитель: `saas-migration-orchestrator` (Phase M6)  
> Принцип: **fork + fix**, не rewrite

---

## Обязательно до внешних арендаторов (M6)

| ID | Проблема | Действие в saas | Cherry-pick в v5? |
| --- | --- | --- | --- |
| **P0.10** | Residual IDOR / `manager_id` scope | Унифицировать через `*ViewAuthorization` + tenant scope | ✅ да |
| **P1.1** | Department scope не в backend | Fix finance, leads queue, payment auto-status | ✅ да |
| **NEW** | Нет tenant isolation | `TenantScope`, isolation tests | ❌ только saas |
| **P2.3** | XSS v-html (Mermaid, PublicSite) | DOMPurify / escape | ✅ да |

### Файлы (grep-шпаргалка)

- `app/Support/PaymentScheduleAutomaticStatus.php`
- `app/Services/Finance/FinanceOverviewService.php`
- `app/Services/Finance/ContractorReconciliationService.php`
- `app/Services/Commercial/LeadAttentionQueueService.php`
- `app/Support/OrderViewAuthorization.php` — эталон

---

## Желательно (M7 или позже)

| ID | Проблема | Действие |
| --- | --- | --- |
| P1.3 | Мёртвые permissions | Wire-up или удалить из UI |
| P1.4 | Legacy order status | Упростить для SaaS MVP |
| P1.5 | Activity timeline admin-only | Решить через OrderViewAuthorization |

---

## Отложить (не блокирует аренду)

| ID | Проблема | Почему |
| --- | --- | --- |
| P2.1 | Order Wizard ~3k строк | Работает; Load Board pattern позже |
| P3.x | UX уплотнение | Косметика |

---

## Gate M6 → M7

- [ ] Isolation test: tenant A ≠ B
- [ ] Grep `manager_id === $user->id` — все в scope helpers
- [ ] Finance overview respects department scope
- [ ] `php artisan test` green for scope/tenant suite

*Обновлено: 2026-07-11*
