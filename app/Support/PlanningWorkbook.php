<?php

namespace App\Support;

/**
 * ADR-023: semantic workbook sheets (named tabs), not an Excel clone.
 */
class PlanningWorkbook
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     host: string,
     *     href: string,
     *     anchor: string|null,
     *     hint: string
     * }>
     */
    public static function sheets(
        bool $canBudgeting,
        bool $canManagementAccounting,
        ?string $activeKey = null,
    ): array {
        $sheets = [];

        if ($canBudgeting) {
            $budgeting = route('budgeting.index');
            $sheets = array_merge($sheets, [
                [
                    'key' => 'drivers',
                    'label' => 'Драйверы',
                    'host' => 'budgeting',
                    'href' => $budgeting.'#sheet-drivers',
                    'anchor' => 'sheet-drivers',
                    'hint' => 'N, маржа/голову, взносы, горизонт',
                ],
                [
                    'key' => 'opex',
                    'label' => 'План OPEX',
                    'host' => 'budgeting',
                    'href' => $budgeting.'#sheet-opex',
                    'anchor' => 'sheet-opex',
                    'hint' => 'Статьи, soft-map → УУ',
                ],
                [
                    'key' => 'timeline',
                    'label' => 'Вехи',
                    'host' => 'budgeting',
                    'href' => $budgeting.'#sheet-timeline',
                    'anchor' => 'sheet-timeline',
                    'hint' => 'Ноль месяца, накопление, пик финансирования',
                ],
                [
                    'key' => 'cvp',
                    'label' => 'CVP',
                    'host' => 'budgeting',
                    'href' => $budgeting.'#sheet-cvp',
                    'anchor' => 'sheet-cvp',
                    'hint' => 'Пост./перем. из typed OPEX — не касса',
                ],
            ]);
        }

        if ($canManagementAccounting) {
            $ua = route('finance.management-accounting.index');
            $sheets = array_merge($sheets, [
                [
                    'key' => 'articles',
                    'label' => 'Статьи',
                    'host' => 'ua',
                    'href' => $ua.'?tab=categories',
                    'anchor' => null,
                    'hint' => 'Дерево статей УУ',
                ],
                [
                    'key' => 'plan_fact',
                    'label' => 'План / факт',
                    'host' => 'ua',
                    'href' => $ua.'?tab=ledger#sheet-plan-fact',
                    'anchor' => 'sheet-plan-fact',
                    'hint' => 'План | факт | Δ',
                ],
                [
                    'key' => 'cash',
                    'label' => 'Касса',
                    'host' => 'ua',
                    'href' => $ua.'?tab=cash',
                    'anchor' => null,
                    'hint' => 'Тонкая ДДС: выписка + ручное',
                ],
            ]);
        }

        return array_map(static function (array $sheet) use ($activeKey): array {
            $sheet['active'] = $activeKey !== null && $sheet['key'] === $activeKey;

            return $sheet;
        }, $sheets);
    }
}
