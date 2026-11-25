<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThinCashOpeningBalanceRequest;
use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use App\Services\ManagementAccounting\ThinCashBalanceService;
use App\Support\PlanningWorkbook;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementAccountingController extends Controller
{
    public function __construct(
        private readonly ManagementAccountingAnalyticsService $analyticsService,
        private readonly ManagementExpenseCategoryTreeService $categoryTreeService,
        private readonly ThinCashBalanceService $thinCash,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);

        ManagementBankAccount::ensureManualInstruments();

        $tab = (string) $request->string('tab');
        if (! in_array($tab, ['ledger', 'categories', 'cash'], true)) {
            $tab = 'ledger';
        }

        $periodType = $this->analyticsService->normalizePeriodType((string) $request->string('period_type'));
        $periodAnchor = $request->input('period_anchor');
        $periodAnchor = is_string($periodAnchor) && $periodAnchor !== '' ? $periodAnchor : null;
        $kindFilter = (string) $request->string('kind');
        if (! in_array($kindFilter, ['', 'all', 'expense', 'income', 'payroll', 'tax'], true)) {
            $kindFilter = 'all';
        }
        if ($kindFilter === '') {
            $kindFilter = 'all';
        }

        $activeWorkbookKey = match ($tab) {
            'categories' => 'articles',
            'cash' => 'cash',
            default => 'plan_fact',
        };

        return Inertia::render('Finance/ManagementAccounting/Index', [
            'filters' => [
                'tab' => $tab,
                'period_type' => $periodType,
                'period_anchor' => $periodAnchor ?? now()->startOfMonth()->toDateString(),
                'kind' => $kindFilter,
            ],
            'workbook' => PlanningWorkbook::sheets(
                canBudgeting: RoleAccess::canAccessBudgeting($request->user())
                    && (TenantContext::get()?->featureEnabled('budgeting') ?? false),
                canManagementAccounting: true,
                activeKey: $activeWorkbookKey,
            ),
            'category_tree' => $this->categoryTreeService->treeForUi(),
            'categories' => ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'code', 'name', 'kind', 'flow', 'is_system', 'include_in_budget'])
                ->map(static fn (ManagementExpenseCategory $category): array => [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'kind' => $category->kind,
                    'flow' => $category->flow ?? 'out',
                    'is_system' => $category->is_system,
                    'include_in_budget' => (bool) $category->include_in_budget,
                    'source' => self::categorySource($category),
                ]),
            'bank_accounts' => ManagementBankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_number', 'account_mask', 'currency', 'opening_balance', 'opening_balance_as_of', 'opening_balance_source'])
                ->map(static fn (ManagementBankAccount $account): array => [
                    'id' => $account->id,
                    'bank_name' => $account->bank_name,
                    'account_mask' => $account->account_mask,
                    'currency' => $account->currency,
                    'instrument' => ManagementBankAccount::instrumentFor($account->account_number),
                    'opening_balance' => $account->opening_balance !== null ? (float) $account->opening_balance : null,
                    'opening_balance_as_of' => $account->opening_balance_as_of?->toDateString(),
                    'opening_balance_source' => $account->opening_balance_source,
                ]),
            'recent_manual_entries' => ManagementStatementLine::query()
                ->where('source', 'manual')
                ->with(['allocationCategory:id,name'])
                ->orderByDesc('operation_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'operation_date', 'direction', 'amount', 'description', 'status', 'allocation_category_id'])
                ->map(static fn (ManagementStatementLine $line): array => [
                    'id' => $line->id,
                    'operation_date' => $line->operation_date?->toDateString(),
                    'direction' => $line->direction,
                    'amount' => (float) $line->amount,
                    'description' => $line->description,
                    'status' => $line->status,
                    'category_name' => $line->allocationCategory?->name,
                ]),
            'can_manage_manual_entries' => RoleAccess::canAccessPaymentReconcile($request->user()),
            'analytics' => $this->analyticsService->build($periodType, $periodAnchor),
            'thin_cash' => $this->thinCash->build(),
            'budgeting_url' => RoleAccess::canAccessBudgeting($request->user())
                && (TenantContext::get()?->featureEnabled('budgeting') ?? false)
                ? route('budgeting.index')
                : null,
        ]);
    }

    public function updateOpeningBalance(UpdateThinCashOpeningBalanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $account = ManagementBankAccount::query()->findOrFail($data['bank_account_id']);
        $account->update([
            'opening_balance' => $data['opening_balance'],
            'opening_balance_as_of' => $data['opening_balance_as_of'] ?? null,
            'opening_balance_source' => $data['opening_balance_source'] ?? 'manual',
        ]);

        return to_route('finance.management-accounting.index', ['tab' => 'cash'])
            ->with('flash', ['type' => 'success', 'message' => 'Стартовое сальдо сохранено.']);
    }

    private static function categorySource(ManagementExpenseCategory $category): string
    {
        if ($category->is_system) {
            return 'system';
        }

        if (str_starts_with($category->code, 'budget_opex_')) {
            return 'budget';
        }

        return 'custom';
    }
}
