<?php

namespace App\Http\Controllers;

use App\Http\Requests\FreezeBudgetPlanSnapshotRequest;
use App\Http\Requests\StoreBudgetOpexArticleRequest;
use App\Http\Requests\UpdateBudgetOpexArticleRequest;
use App\Http\Requests\UpdateBudgetSalesTargetsRequest;
use App\Http\Requests\UpdateBudgetScenarioRequest;
use App\Models\BudgetOpexArticle;
use App\Models\BudgetScenario;
use App\Models\ManagementExpenseCategory;
use App\Services\Budgeting\BudgetMarginBenchmarkService;
use App\Services\Budgeting\BudgetMinIcpOpexSeedService;
use App\Services\Budgeting\BudgetPlannerService;
use App\Services\Budgeting\BudgetPlanSnapshotService;
use App\Services\Budgeting\BudgetSalesTargetService;
use App\Services\CompanyPlanning\CompanyPlanningBudgetLinkService;
use App\Support\PlanningWorkbook;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BudgetingController extends Controller
{
    public function __construct(
        private readonly BudgetPlannerService $planner,
        private readonly BudgetMarginBenchmarkService $benchmarks,
        private readonly BudgetPlanSnapshotService $snapshotService,
        private readonly CompanyPlanningBudgetLinkService $companyPlanningBudgetLinks,
        private readonly BudgetSalesTargetService $salesTargetService,
        private readonly BudgetMinIcpOpexSeedService $minIcpSeed,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessBudgeting($request->user()), 403);

        $scenario = $this->resolveScenario();
        $opexArticles = $this->resolveOpexArticles($scenario);
        $inputs = $this->planner->normalizeInputs($scenario->inputs ?? []);
        $dbBenchmark = $this->benchmarks->lastMonthsSummary(6);
        $plan = $this->planner->buildPlan($inputs, $opexArticles, $dbBenchmark);

        return Inertia::render('Budgeting/Index', [
            'inputs' => $inputs,
            'plan' => $plan,
            'cvp' => $this->buildCvpReadModel($opexArticles, $inputs),
            'opex_articles' => $opexArticles,
            'expense_categories' => $this->expenseCategoriesForSoftMap(),
            'soft_map_ready' => collect($opexArticles)->every(
                fn (array $article): bool => ($article['management_expense_category_id'] ?? null) !== null,
            ) && count($opexArticles) > 0,
            'workbook' => PlanningWorkbook::sheets(
                canBudgeting: true,
                canManagementAccounting: RoleAccess::canAccessManagementAccounting($request->user())
                    && (TenantContext::get()?->featureEnabled('management_accounting') ?? false),
                activeKey: 'opex',
            ),
            'management_accounting_categories_url' => route('finance.management-accounting.index', ['tab' => 'categories']),
            'management_accounting_plan_fact_url' => route('finance.management-accounting.index', ['tab' => 'ledger']),
            'db_benchmark' => $dbBenchmark,
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'updated_at' => optional($scenario->updated_at)?->toIso8601String(),
            ],
            'sales_plan' => $this->salesTargetService->buildPayload(
                $scenario,
                $request->query('sales_month'),
                (int) $inputs['horizon_months'],
            ),
            'plan_snapshots' => $this->snapshotService->recentSnapshots(),
            'can_freeze_plan' => RoleAccess::canAccessBudgeting($request->user()),
            'company_planning_initiatives' => RoleAccess::canAccessCompanyPlanning($request->user())
                ? $this->companyPlanningBudgetLinks->initiativesLinkedToCategories()
                : [],
            'company_planning_index_url' => RoleAccess::canAccessCompanyPlanning($request->user())
                ? route('company-planning.index')
                : null,
        ]);
    }

    public function updateSalesTargets(UpdateBudgetSalesTargetsRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();

        $this->salesTargetService->upsert(
            $scenario,
            CarbonImmutable::parse($request->validated('period_month'))->startOfMonth(),
            $request->validated('targets'),
        );

        return to_route('budgeting.index', [
            'sales_month' => CarbonImmutable::parse($request->validated('period_month'))->format('Y-m-01'),
        ])->with('flash', ['type' => 'success', 'message' => 'План продавцов сохранён.']);
    }

    public function freezePlan(FreezeBudgetPlanSnapshotRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();

        $this->snapshotService->freeze(
            $scenario,
            CarbonImmutable::parse($request->validated('period_start'))->startOfDay(),
            CarbonImmutable::parse($request->validated('period_end'))->startOfDay(),
            $request->validated('period_label'),
            $request->user(),
            $request->validated('notes'),
        );

        return to_route('budgeting.index')
            ->with('flash', ['type' => 'success', 'message' => 'План зафиксирован. Официальные отклонения план/факт — по этому снимку.']);
    }

    public function updateScenario(UpdateBudgetScenarioRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();
        $inputs = $this->planner->normalizeInputs($request->validated('inputs'));

        $scenario->update([
            'inputs' => $inputs,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return to_route('budgeting.index');
    }

    public function storeOpexArticle(StoreBudgetOpexArticleRequest $request): RedirectResponse
    {
        $maxOrder = (int) BudgetOpexArticle::query()->max('sort_order');

        BudgetOpexArticle::query()->create([
            ...$request->validated(),
            'sort_order' => $maxOrder + 10,
        ]);

        return to_route('budgeting.index');
    }

    public function updateOpexArticle(UpdateBudgetOpexArticleRequest $request, BudgetOpexArticle $opexArticle): RedirectResponse
    {
        $opexArticle->update(collect($request->validated())->only([
            'cost_type',
            'amount_basis',
            'amount_monthly',
            'percent_of_margin',
            'ramp_months',
            'management_expense_category_id',
        ])->all());

        return to_route('budgeting.index');
    }

    public function destroyOpexArticle(Request $request, BudgetOpexArticle $opexArticle): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessBudgeting($request->user()), 403);

        $opexArticle->delete();

        return to_route('budgeting.index');
    }

    private function resolveScenario(): BudgetScenario
    {
        $scenario = BudgetScenario::query()->orderBy('id')->first();

        if ($scenario !== null) {
            return $scenario;
        }

        return BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => BudgetPlannerService::defaultInputs(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveOpexArticles(BudgetScenario $scenario): array
    {
        $articles = BudgetOpexArticle::query()
            ->with('managementExpenseCategory:id,name,code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($articles->isEmpty()) {
            $this->minIcpSeed->seedIfEmpty($scenario->inputs ?? []);
            $articles = BudgetOpexArticle::query()
                ->with('managementExpenseCategory:id,name,code')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return $articles->map(fn (BudgetOpexArticle $article): array => [
            'id' => $article->id,
            'name' => $article->managementExpenseCategory?->name ?? $article->name,
            'management_expense_category_id' => $article->management_expense_category_id,
            'category_code' => $article->managementExpenseCategory?->code,
            'soft_mapped' => $article->management_expense_category_id !== null,
            'cost_type' => $article->cost_type ?? BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_basis' => $article->amount_basis ?? BudgetOpexArticle::AMOUNT_BASIS_TOTAL,
            'amount_monthly' => (float) $article->amount_monthly,
            'percent_of_margin' => $article->percent_of_margin !== null ? (float) $article->percent_of_margin : null,
            'ramp_months' => $article->ramp_months,
            'sort_order' => (int) $article->sort_order,
        ])->values()->all();
    }

    /**
     * @return list<array{id: int, name: string, code: string|null}>
     */
    private function expenseCategoriesForSoftMap(): array
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return [];
        }

        return ManagementExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(static fn (ManagementExpenseCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'code' => $category->code,
            ])
            ->values()
            ->all();
    }

    /**
     * Honest CVP from typed OPEX — fixed vs % of margin. Not cash.
     *
     * @param  list<array<string, mixed>>  $opexArticles
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function buildCvpReadModel(array $opexArticles, array $inputs): array
    {
        $managerCount = max(1, (int) ($inputs['manager_count'] ?? 1));
        $fixed = 0.0;
        $variablePercent = 0.0;

        foreach ($opexArticles as $article) {
            if (($article['cost_type'] ?? '') === BudgetOpexArticle::COST_PERCENT_OF_MARGIN) {
                $variablePercent += (float) ($article['percent_of_margin'] ?? 0);

                continue;
            }

            $amount = (float) ($article['amount_monthly'] ?? 0);
            if (($article['amount_basis'] ?? BudgetOpexArticle::AMOUNT_BASIS_TOTAL) === BudgetOpexArticle::AMOUNT_BASIS_PER_HEAD) {
                $amount *= $managerCount;
                $contrib = max(0, (float) ($inputs['payroll_contributions_percent'] ?? 0)) / 100;
                $amount *= (1 + $contrib);
            }
            $fixed += $amount;
        }

        return [
            'fixed_monthly' => round($fixed, 2),
            'variable_percent_of_margin' => round($variablePercent, 2),
            'disclaimer' => 'CVP по typed OPEX (маржа − расходы). Это не касса и не тонкая ДДС из выписки.',
        ];
    }
}
