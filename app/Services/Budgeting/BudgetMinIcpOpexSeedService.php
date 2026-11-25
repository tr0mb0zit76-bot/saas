<?php

namespace App\Services\Budgeting;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-023 P0: minimal ICP OPEX starter on first enter (full catalog = P1).
 */
class BudgetMinIcpOpexSeedService
{
    /**
     * @param  array<string, mixed>  $legacyInputs
     * @return list<BudgetOpexArticle>
     */
    public function seedIfEmpty(array $legacyInputs = []): array
    {
        if (BudgetOpexArticle::query()->exists()) {
            return [];
        }

        $managerCount = max(1, (int) ($legacyInputs['manager_count'] ?? 3));
        $legacyPayrollLump = (float) ($legacyInputs['manager_payroll_monthly'] ?? 75_000);
        $payrollRate = round($legacyPayrollLump / $managerCount, 2);

        $rows = [
            [
                'name' => 'Офис',
                'code_hint' => 'office',
                'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                'amount_monthly' => (float) ($legacyInputs['office_monthly'] ?? 100_000),
                'percent_of_margin' => null,
                'ramp_months' => null,
                'sort_order' => 10,
            ],
            [
                'name' => 'Бухгалтерия',
                'code_hint' => 'accounting',
                'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                'amount_monthly' => (float) ($legacyInputs['accounting_monthly'] ?? 200_000),
                'percent_of_margin' => null,
                'ramp_months' => null,
                'sort_order' => 20,
            ],
            [
                'name' => 'Оклады менеджеров',
                'code_hint' => 'payroll_managers',
                'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD,
                'amount_monthly' => $payrollRate,
                'percent_of_margin' => null,
                'ramp_months' => null,
                'sort_order' => 30,
            ],
            [
                'name' => 'Аренда',
                'code_hint' => 'rent',
                'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                'amount_monthly' => 80_000,
                'percent_of_margin' => null,
                'ramp_months' => null,
                'sort_order' => 40,
            ],
            [
                'name' => 'IT / рабочие места',
                'code_hint' => 'it',
                'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                'amount_monthly' => 40_000,
                'percent_of_margin' => null,
                'ramp_months' => null,
                'sort_order' => 50,
            ],
            [
                'name' => 'Маркетинг',
                'code_hint' => 'marketing',
                'cost_type' => BudgetOpexArticle::COST_PERCENT_OF_MARGIN,
                'amount_basis' => BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
                'amount_monthly' => 0,
                'percent_of_margin' => 5.0,
                'ramp_months' => null,
                'sort_order' => 60,
            ],
        ];

        $created = [];

        foreach ($rows as $row) {
            $categoryId = $this->resolveCategoryId($row['code_hint'], $row['name']);
            unset($row['code_hint']);

            $created[] = BudgetOpexArticle::query()->create([
                ...$row,
                'management_expense_category_id' => $categoryId,
            ]);
        }

        return $created;
    }

    private function resolveCategoryId(string $codeHint, string $name): ?int
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return null;
        }

        $byCode = ManagementExpenseCategory::query()
            ->where('code', $codeHint)
            ->value('id');

        if ($byCode !== null) {
            return (int) $byCode;
        }

        $byName = ManagementExpenseCategory::query()
            ->where('name', $name)
            ->value('id');

        return $byName !== null ? (int) $byName : null;
    }
}
