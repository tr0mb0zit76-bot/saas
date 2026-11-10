<?php

namespace Tests\Unit;

use App\Services\Budgeting\BudgetPlannerService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BudgetTopDownPlannerServiceTest extends TestCase
{
    private BudgetPlannerService $planner;

    /**
     * @var list<array{name: string, amount_monthly: float, ramp_months: ?int}>
     */
    private array $opexArticles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new BudgetPlannerService;
        $this->opexArticles = [
            ['name' => 'Офис', 'amount_monthly' => 100_000, 'ramp_months' => null],
            ['name' => 'Бухгалтерия', 'amount_monthly' => 200_000, 'ramp_months' => null],
            ['name' => 'Оклады', 'amount_monthly' => 75_000, 'ramp_months' => 3],
        ];
    }

    #[Test]
    public function top_down_plan_milestone_has_zero_monthly_net_but_cash_breakeven_is_later(): void
    {
        $inputs = array_merge(BudgetPlannerService::defaultInputs(), [
            'calculation_mode' => BudgetPlannerService::MODE_TOP_DOWN,
            'breakeven_month' => 4,
            'owner_investment' => 300_000,
        ]);
        $plan = $this->planner->buildPlan($inputs, $this->opexArticles);
        $planMonth = collect($plan['months'])->firstWhere('month', 4);

        $this->assertNotNull($planMonth);
        $this->assertEqualsWithDelta(0.0, (float) $planMonth['net'], 0.01);
        $this->assertLessThan(0.0, (float) $planMonth['cumulative']);
        $this->assertSame(4, $plan['summary']['plan_milestone_month']);
        $this->assertSame(4, $plan['summary']['breakeven_month_operating']);
        $this->assertGreaterThan(4, $plan['summary']['breakeven_month_cash'] ?? 99);
        $this->assertSame($plan['summary']['breakeven_month_cash'], $plan['summary']['breakeven_month']);
        $this->assertNotSame(
            $plan['summary']['breakeven_month_operating'],
            $plan['summary']['breakeven_month'],
        );
    }

    #[Test]
    public function top_down_margin_ramp_never_decreases_before_target_month(): void
    {
        $inputs = array_merge(BudgetPlannerService::defaultInputs(), [
            'calculation_mode' => BudgetPlannerService::MODE_TOP_DOWN,
            'breakeven_month' => 4,
            'cash_zero_month' => 12,
            'target_dividends_month' => 12,
            'owner_investment' => 300_000,
        ]);

        $plan = $this->planner->buildPlan($inputs, $this->opexArticles);
        $margins = collect($plan['months'])->pluck('margin_per_manager')->all();

        for ($i = 1; $i < count($margins); $i++) {
            $this->assertGreaterThanOrEqual(
                $margins[$i - 1] - 0.01,
                $margins[$i],
                "Margin must not drop between months {$i} and ".($i + 1),
            );
        }
    }

    #[Test]
    public function estimate_cash_breakeven_month_extrapolates_from_positive_monthly_net(): void
    {
        $method = new \ReflectionMethod(BudgetPlannerService::class, 'estimateCashBreakevenMonth');
        $method->setAccessible(true);

        $estimated = $method->invoke($this->planner, [
            ['month' => 6, 'net' => 80_250.0, 'cumulative' => -802_500.0],
        ]);

        $this->assertSame(16, $estimated);
    }

    #[Test]
    public function top_down_calculates_manager_x_and_y(): void
    {
        $inputs = BudgetPlannerService::defaultInputs();
        $plan = $this->planner->buildPlan($inputs, $this->opexArticles);
        $managers = (int) $inputs['manager_count'];

        $this->assertEqualsWithDelta(
            $plan['summary']['required_margin_target'] / $managers,
            $plan['summary']['manager_target_x'],
            0.01,
        );
    }

    #[Test]
    public function bottom_up_finds_operating_breakeven_month(): void
    {
        $inputs = array_merge(BudgetPlannerService::defaultInputs(), [
            'calculation_mode' => BudgetPlannerService::MODE_BOTTOM_UP,
            'margin_per_manager' => 120_000,
            'manager_count' => 3,
        ]);

        $plan = $this->planner->buildPlan($inputs, $this->opexArticles);

        $this->assertNotNull($plan['summary']['breakeven_month_operating']);
        $this->assertGreaterThan(0, $plan['summary']['breakeven_month_operating']);
    }

    #[Test]
    public function percent_of_margin_increases_required_company_margin(): void
    {
        $articles = [
            ['name' => 'Фикс', 'cost_type' => 'fixed_monthly', 'amount_monthly' => 300_000, 'percent_of_margin' => null, 'ramp_months' => null],
            ['name' => 'Бонус', 'cost_type' => 'percent_of_margin', 'amount_monthly' => 0, 'percent_of_margin' => 10, 'ramp_months' => null],
        ];

        $inputs = BudgetPlannerService::defaultInputs();
        $marginWithoutPercent = (new BudgetPlannerService)->marginForTargetNet(4, [$articles[0]], 0.0, $inputs);
        $marginWithPercent = (new BudgetPlannerService)->marginForTargetNet(4, $articles, 0.0, $inputs);

        $this->assertEqualsWithDelta(300_000, $marginWithoutPercent, 0.01);
        $this->assertEqualsWithDelta(333_333.33, $marginWithPercent, 1.0);
    }

    #[Test]
    public function opex_ramp_drops_payroll_after_month_three(): void
    {
        $inputs = BudgetPlannerService::defaultInputs();

        $this->assertGreaterThan(
            $this->planner->monthlyOpex(4, $this->opexArticles, 0.0, $inputs),
            $this->planner->monthlyOpex(2, $this->opexArticles, 0.0, $inputs),
        );
    }

    #[Test]
    public function per_head_salary_scales_with_manager_count_and_contributions(): void
    {
        $articles = [
            [
                'name' => 'Офис',
                'cost_type' => 'fixed_monthly',
                'amount_basis' => 'total',
                'amount_monthly' => 100_000,
                'ramp_months' => null,
            ],
            [
                'name' => 'Оклады менеджеров',
                'cost_type' => 'fixed_monthly',
                'amount_basis' => 'per_head',
                'amount_monthly' => 150_000, // Orientum 450k lump ÷ 3
                'ramp_months' => null,
            ],
        ];

        $inputsN3 = array_merge(BudgetPlannerService::defaultInputs(), [
            'manager_count' => 3,
            'payroll_contributions_percent' => 30.2,
        ]);
        $inputsN4 = array_merge($inputsN3, ['manager_count' => 4]);

        $opex3 = $this->planner->monthlyFixedOpex(1, $articles, $inputsN3);
        $opex4 = $this->planner->monthlyFixedOpex(1, $articles, $inputsN4);

        // 100k + 150k×3 + 30.2%×450k = 100k + 450k + 135900 = 685900
        $this->assertEqualsWithDelta(685_900.0, $opex3, 0.01);
        // 100k + 150k×4 + 30.2%×600k = 100k + 600k + 181200 = 881200
        $this->assertEqualsWithDelta(881_200.0, $opex4, 0.01);
        $this->assertGreaterThan($opex3, $opex4);
    }

    #[Test]
    public function lump_total_article_ignores_manager_count(): void
    {
        $articles = [[
            'name' => 'Офис',
            'cost_type' => 'fixed_monthly',
            'amount_basis' => 'total',
            'amount_monthly' => 100_000,
            'ramp_months' => null,
        ]];

        $n1 = array_merge(BudgetPlannerService::defaultInputs(), [
            'manager_count' => 1,
            'payroll_contributions_percent' => 30.2,
        ]);
        $n5 = array_merge($n1, ['manager_count' => 5]);

        $this->assertEqualsWithDelta(
            $this->planner->monthlyFixedOpex(1, $articles, $n1),
            $this->planner->monthlyFixedOpex(1, $articles, $n5),
            0.01,
        );
    }

    #[Test]
    public function plan_summary_includes_formula_version_and_peak_funding(): void
    {
        $inputs = array_merge(BudgetPlannerService::defaultInputs(), [
            'calculation_mode' => BudgetPlannerService::MODE_BOTTOM_UP,
            'margin_per_manager' => 50_000,
            'manager_count' => 3,
            'owner_investment' => 100_000,
            'payroll_contributions_percent' => 0,
        ]);

        $plan = $this->planner->buildPlan($inputs, $this->opexArticles);

        $this->assertSame(BudgetPlannerService::FORMULA_VERSION, $plan['summary']['formula_version']);
        $this->assertArrayHasKey('peak_funding', $plan['summary']);
        $this->assertEqualsWithDelta(
            max(0, -((float) $plan['summary']['min_cumulative'])),
            (float) $plan['summary']['peak_funding'],
            0.01,
        );
    }
}
