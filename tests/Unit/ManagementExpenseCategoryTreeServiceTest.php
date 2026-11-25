<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementExpenseCategoryHierarchyService;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use Tests\TestCase;

class ManagementExpenseCategoryTreeServiceTest extends TestCase
{
    public function test_creates_nested_category_under_group(): void
    {
        $group = $this->createManagementExpenseCategory([
            'code' => 'group_payroll_test',
            'name' => 'ФОТ',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'sort_order' => 10,
        ]);

        $created = app(ManagementExpenseCategoryTreeService::class)->create('Бухгалтерия', $group->id);

        $category = ManagementExpenseCategory::query()->findOrFail($created['id']);

        $this->assertSame($group->id, $category->parent_id);
        $this->assertSame('Бухгалтерия', $category->name);
    }

    public function test_builds_tree_for_ui(): void
    {
        $root = $this->createManagementExpenseCategory([
            'code' => 'group_expense_test',
            'name' => 'Расходы тест',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'sort_order' => 10,
        ]);

        $this->createManagementExpenseCategory([
            'parent_id' => $root->id,
            'code' => 'custom_test',
            'name' => 'Аренда',
            'kind' => 'overhead',
            'flow' => 'out',
            'is_system' => false,
            'sort_order' => 20,
        ]);

        $tree = app(ManagementExpenseCategoryTreeService::class)->treeForUi();

        $expenseRoot = collect($tree)->firstWhere('code', 'group_expense_test');
        $this->assertNotNull($expenseRoot);
        $this->assertCount(1, $expenseRoot['children']);
        $this->assertSame('Аренда', $expenseRoot['children'][0]['name']);
    }

    public function test_sync_promotes_empty_root_groups_to_expense_leaves(): void
    {
        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $leasing = ManagementExpenseCategory::query()->create([
            'code' => 'budget_opex_77',
            'name' => 'Лизинг',
            'kind' => 'group',
            'flow' => 'out',
            'parent_id' => null,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 90,
        ]);

        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $leasing->refresh();
        $expenseRootId = ManagementExpenseCategory::query()->where('code', 'group_expense')->value('id');

        $this->assertSame('overhead', $leasing->kind);
        $this->assertSame($expenseRootId, $leasing->parent_id);
        $this->assertTrue($leasing->is_active);
    }

    public function test_sync_keeps_tree_budget_opex_leaf_active_for_manual_entry(): void
    {
        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $expenseRootId = (int) ManagementExpenseCategory::query()->where('code', 'group_expense')->value('id');

        $fuel = ManagementExpenseCategory::query()->create([
            'code' => 'budget_opex_88',
            'name' => 'ГСМ',
            'kind' => 'overhead',
            'flow' => 'out',
            'parent_id' => $expenseRootId,
            'is_system' => false,
            'is_active' => false,
            'sort_order' => 95,
        ]);

        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $fuel->refresh();

        $this->assertTrue($fuel->is_active);
        $this->assertSame('overhead', $fuel->kind);
        $this->assertTrue(
            ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->where('kind', '!=', 'group')
                ->where('flow', 'out')
                ->whereKey($fuel->id)
                ->exists(),
        );
    }

    public function test_heal_selectable_expense_articles_without_full_sync(): void
    {
        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $expenseRootId = (int) ManagementExpenseCategory::query()->where('code', 'group_expense')->value('id');

        $fuel = ManagementExpenseCategory::query()->create([
            'code' => 'budget_opex_99',
            'name' => 'Топливо',
            'kind' => 'overhead',
            'flow' => 'out',
            'parent_id' => $expenseRootId,
            'is_system' => false,
            'is_active' => false,
            'sort_order' => 96,
        ]);

        app(ManagementExpenseCategoryHierarchyService::class)->healSelectableExpenseArticles();

        $this->assertTrue($fuel->fresh()->is_active);
    }
}
