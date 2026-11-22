<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Services\ManagementAccounting\ThinCashBalanceService;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\RoleAccess;
use App\Support\ScopedPaymentScheduleQuery;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-024 P1: control dashboard over payment schedules for /finance?section=cashflow.
 * Plan|факт from schedule statuses; thin cash «сейчас» only when management_accounting.
 */
class CashFlowControlDashboardService
{
    public function __construct(
        private readonly ThinCashBalanceService $thinCash,
    ) {}

    /**
     * @param  array{
     *     period?: string,
     *     flow?: string,
     *     overlay?: string,
     *     direction?: string|null,
     *     anchor?: string|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function build(?User $user, array $filters = []): array
    {
        $period = $this->normalizePeriod($filters['period'] ?? 'M');
        $flow = $this->normalizeFlow($filters['flow'] ?? 'total');
        $overlay = $this->normalizeOverlay($filters['overlay'] ?? 'fact_plus_expectations');
        $direction = $this->normalizeDirection($filters['direction'] ?? null);
        $anchor = $this->resolveAnchor($filters['anchor'] ?? null);
        [$periodStart, $periodEnd] = $this->periodBounds($period, $anchor);

        $tenant = TenantContext::get();
        // Thin cash «Сейчас» = Enterprise UA feature + role (ADR-024/025); not schedules alone.
        $hasThinCash = (bool) ($tenant?->featureEnabled('management_accounting'))
            && RoleAccess::canAccessManagementAccounting($user);
        $mode = $hasThinCash ? 'schedules_plus_bank' : 'schedules_only';

        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('orders')) {
            return $this->emptyPayload($mode, $hasThinCash, $period, $flow, $overlay, $direction, $periodStart, $periodEnd);
        }

        $rows = $this->scheduleRows($user, $periodStart, $periodEnd, $direction);

        $gauges = [
            'incomes' => $this->gaugeForFlow($rows, 'incomes'),
            'expenses' => $this->gaugeForFlow($rows, 'expenses'),
            'total' => $this->gaugeForFlow($rows, 'total'),
        ];

        $accuracy = $this->accuracySeries($rows, $periodStart, $periodEnd);
        $byDirection = $this->breakdownByDirection($user, $periodStart, $periodEnd, $flow);
        $directionOptions = $this->directionOptions($user);

        $expectations = $this->expectationsOverlay($user, $direction);
        $cashNow = null;
        if ($hasThinCash) {
            $thin = $this->thinCash->build($periodEnd);
            $cashNow = [
                'balance_now' => (float) $thin['balance_now'],
                'label' => (string) $thin['label'],
                'disclaimer' => (string) $thin['disclaimer'],
                'opening_source' => (string) $thin['opening_source'],
            ];
        }

        $projection = null;
        if ($overlay === 'fact_plus_expectations') {
            $projection = [
                'cash_now' => $cashNow['balance_now'] ?? null,
                'expected_in' => $expectations['expected_in'],
                'expected_out' => $expectations['expected_out'],
                'projected' => $cashNow !== null
                    ? round(($cashNow['balance_now'] + $expectations['expected_in'] - $expectations['expected_out']), 2)
                    : null,
                'note' => $cashNow === null
                    ? 'Проекция без «кассы сейчас»: только ожидания ДЗ/КЗ по графику (Pro).'
                    : 'Касса сейчас + ожид. поступления − ожид. оплаты (не начисление).',
            ];
        }

        return [
            'mode' => $mode,
            'period' => $period,
            'period_options' => [
                ['value' => 'M', 'label' => 'Месяц'],
                ['value' => 'Q', 'label' => 'Квартал'],
                ['value' => 'H', 'label' => 'Полугодие'],
                ['value' => 'Y', 'label' => 'Год'],
            ],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'flow' => $flow,
            'overlay' => $overlay,
            'direction' => $direction,
            'direction_options' => $directionOptions,
            'gauges' => $gauges,
            'active_gauge' => $gauges[$flow],
            'accuracy' => $accuracy,
            'by_direction' => $byDirection,
            'lenses' => [
                'cash_now' => [
                    'key' => 'cash_now',
                    'label' => 'Сейчас',
                    'subtitle' => 'Только касса / банк (не ДЗ/КЗ)',
                    'available' => $hasThinCash,
                    'value' => $cashNow,
                ],
                'expectations' => [
                    'key' => 'expectations',
                    'label' => 'Факт + ожидания (ДЗ/КЗ)',
                    'subtitle' => 'Оверлей неоплаченных строк графика',
                    'available' => true,
                    'value' => $expectations,
                ],
                'management_pnl' => [
                    'key' => 'management_pnl',
                    'label' => 'Упр. ОПиУ',
                    'subtitle' => 'Начисление по заказам — отдельно, не касса',
                    'available' => false,
                    'stub' => true,
                    'period' => $period,
                    'message' => 'Управленческий ОПиУ (M/Q/H/Y) — P2. Здесь только селектор периода для будущего отчёта; не путать с графиком оплат.',
                ],
            ],
            'projection' => $projection,
            'labels' => [
                'plan' => 'План (график)',
                'fact' => 'Факт (оплачено по графику)',
                'bank_fact_deferred' => $mode === 'schedules_plus_bank'
                    ? 'Join банковской выписки в gauges — P2; «Сейчас» уже из тонкой кассы.'
                    : null,
            ],
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function scheduleRows(?User $user, CarbonImmutable $from, CarbonImmutable $to, ?string $direction): Collection
    {
        $query = $this->baseScheduleQuery($user)
            ->whereDate('payment_schedules.planned_date', '>=', $from->toDateString())
            ->whereDate('payment_schedules.planned_date', '<=', $to->toDateString());

        $this->applyDirectionFilter($query, $direction);

        $select = [
            'payment_schedules.id',
            'payment_schedules.party',
            'payment_schedules.amount',
            'payment_schedules.status',
            'payment_schedules.planned_date',
            'payment_schedules.actual_date',
            'orders.id as order_id',
            'orders.own_company_id',
        ];

        if (Schema::hasColumn('orders', 'is_international_transport')) {
            $select[] = 'orders.is_international_transport';
        }

        if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
            $select[] = DB::raw('COALESCE(payment_schedules.paid_amount, 0) as paid_amount');
        } else {
            $select[] = DB::raw('0 as paid_amount');
        }

        return $query->select($select)->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{plan: float, fact: float, pct: float|null, label: string}
     */
    private function gaugeForFlow(Collection $rows, string $flow): array
    {
        $filtered = $rows->filter(fn (object $row): bool => $this->rowMatchesFlow($row, $flow));

        $plan = round((float) $filtered->sum(fn (object $row): float => (float) $row->amount), 2);
        $fact = round((float) $filtered->sum(function (object $row): float {
            $paid = (float) ($row->paid_amount ?? 0);
            if ($paid > 0.009) {
                return $paid;
            }

            return strtolower((string) $row->status) === 'paid' ? (float) $row->amount : 0.0;
        }), 2);

        return [
            'plan' => $plan,
            'fact' => $fact,
            'pct' => $plan > 0.009 ? round(($fact / $plan) * 100, 1) : null,
            'label' => match ($flow) {
                'incomes' => 'Поступления',
                'expenses' => 'Списания',
                default => 'Итого',
            },
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{bucket: string, label: string, plan: float, fact: float, pct: float|null}>
     */
    private function accuracySeries(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cursor = $from->startOfWeek(CarbonImmutable::MONDAY);
        $end = $to->endOfWeek(CarbonImmutable::SUNDAY);
        $series = [];

        while ($cursor->lte($end)) {
            $weekStart = $cursor;
            $weekEnd = $cursor->endOfWeek(CarbonImmutable::SUNDAY);
            $bucketRows = $rows->filter(function (object $row) use ($weekStart, $weekEnd): bool {
                $planned = CarbonImmutable::parse((string) $row->planned_date)->startOfDay();

                return $planned->betweenIncluded($weekStart->startOfDay(), $weekEnd->startOfDay());
            });

            $gauge = $this->gaugeForFlow($bucketRows, 'total');
            $series[] = [
                'bucket' => $weekStart->toDateString(),
                'label' => $weekStart->format('d.m').'–'.$weekEnd->format('d.m'),
                'plan' => $gauge['plan'],
                'fact' => $gauge['fact'],
                'pct' => $gauge['pct'],
            ];

            $cursor = $cursor->addWeek();
            if (count($series) > 26) {
                break;
            }
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, plan: float, fact: float, pct: float|null}>
     */
    private function breakdownByDirection(?User $user, CarbonImmutable $from, CarbonImmutable $to, string $flow): array
    {
        $rows = $this->scheduleRows($user, $from, $to, null);
        $companyNames = $this->ownCompanyNames(
            $rows->pluck('own_company_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all()
        );

        $groups = $rows->groupBy(function (object $row) use ($companyNames): string {
            $ownId = isset($row->own_company_id) ? (int) $row->own_company_id : 0;
            if ($ownId > 0) {
                return 'own_company:'.$ownId;
            }

            $intl = Schema::hasColumn('orders', 'is_international_transport')
                && (bool) ($row->is_international_transport ?? false);

            return $intl ? 'transport:international' : 'transport:domestic';
        });

        $out = [];
        foreach ($groups as $key => $group) {
            $gauge = $this->gaugeForFlow($group->filter(fn (object $row): bool => $this->rowMatchesFlow($row, $flow)), $flow);
            $out[] = [
                'key' => (string) $key,
                'label' => $this->directionLabel((string) $key, $companyNames),
                'plan' => $gauge['plan'],
                'fact' => $gauge['fact'],
                'pct' => $gauge['pct'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['plan'] <=> $a['plan']);

        return $out;
    }

    /**
     * @return list<array{value: string|null, label: string}>
     */
    private function directionOptions(?User $user): array
    {
        $options = [
            ['value' => null, 'label' => 'Все направления'],
            ['value' => 'transport:domestic', 'label' => 'Внутренние'],
            ['value' => 'transport:international', 'label' => 'Международные (ВЭД)'],
        ];

        if (! Schema::hasColumn('orders', 'own_company_id') || ! Schema::hasTable('contractors')) {
            return $options;
        }

        $query = $this->baseScheduleQuery($user)
            ->whereNotNull('orders.own_company_id')
            ->select('orders.own_company_id')
            ->distinct();

        $ids = $query->pluck('own_company_id')->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        $names = $this->ownCompanyNames($ids);

        foreach ($ids as $id) {
            $options[] = [
                'value' => 'own_company:'.$id,
                'label' => $names[$id] ?? ('Юрлицо #'.$id),
            ];
        }

        return $options;
    }

    /**
     * @return array{expected_in: float, expected_out: float, receivables: float, payables: float}
     */
    private function expectationsOverlay(?User $user, ?string $direction): array
    {
        $query = $this->baseScheduleQuery($user);
        PaymentScheduleSettlementStatus::applyUnsettledRootScope($query);
        $this->applyDirectionFilter($query, $direction);

        $effective = PaymentScheduleSettlementStatus::outstandingAmountSql();
        $rows = $query
            ->selectRaw(
                'LOWER(TRIM(payment_schedules.party)) as party, SUM('.$effective.') as amount'
            )
            ->groupBy(DB::raw('LOWER(TRIM(payment_schedules.party))'))
            ->get()
            ->keyBy('party');

        $expectedIn = (float) (optional($rows->get('customer'))->amount ?? 0);
        $expectedOut = (float) (optional($rows->get('carrier'))->amount ?? 0)
            + (float) (optional($rows->get('contractor'))->amount ?? 0);

        return [
            'expected_in' => round($expectedIn, 2),
            'expected_out' => round($expectedOut, 2),
            'receivables' => round($expectedIn, 2),
            'payables' => round($expectedOut, 2),
        ];
    }

    private function baseScheduleQuery(?User $user)
    {
        return ScopedPaymentScheduleQuery::base($user);
    }

    private function applyDirectionFilter($query, ?string $direction): void
    {
        if ($direction === null || $direction === '' || $direction === 'all') {
            return;
        }

        if (str_starts_with($direction, 'own_company:')) {
            $id = (int) substr($direction, strlen('own_company:'));
            if ($id > 0 && Schema::hasColumn('orders', 'own_company_id')) {
                $query->where('orders.own_company_id', $id);
            }

            return;
        }

        if ($direction === 'transport:international' && Schema::hasColumn('orders', 'is_international_transport')) {
            $query->where('orders.is_international_transport', true);

            return;
        }

        if ($direction === 'transport:domestic' && Schema::hasColumn('orders', 'is_international_transport')) {
            $query->where(function ($q): void {
                $q->whereNull('orders.is_international_transport')
                    ->orWhere('orders.is_international_transport', false);
            });
        }
    }

    private function rowMatchesFlow(object $row, string $flow): bool
    {
        $party = strtolower(trim((string) ($row->party ?? '')));
        $isIncome = $party === 'customer';

        return match ($flow) {
            'incomes' => $isIncome,
            'expenses' => ! $isIncome,
            default => true,
        };
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function ownCompanyNames(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('contractors')) {
            return [];
        }

        $nameSql = Schema::hasColumn('contractors', 'full_name')
            ? "COALESCE(NULLIF(TRIM(name), ''), full_name)"
            : 'name';

        $query = DB::table('contractors')->whereIn('id', $ids);
        if (Schema::hasColumn('contractors', 'tenant_id')) {
            TenantContext::restrict($query, 'contractors.tenant_id');
        }

        return $query
            ->selectRaw("id, {$nameSql} as label")
            ->pluck('label', 'id')
            ->map(fn ($label): string => (string) $label)
            ->all();
    }

    /**
     * @param  array<int, string>  $companyNames
     */
    private function directionLabel(string $key, array $companyNames): string
    {
        if (str_starts_with($key, 'own_company:')) {
            $id = (int) substr($key, strlen('own_company:'));

            return $companyNames[$id] ?? ('Юрлицо #'.$id);
        }

        return match ($key) {
            'transport:international' => 'Международные (ВЭД)',
            'transport:domestic' => 'Внутренние / без юрлица',
            default => $key,
        };
    }

    private function normalizePeriod(string $period): string
    {
        $period = strtoupper($period);

        return in_array($period, ['M', 'Q', 'H', 'Y'], true) ? $period : 'M';
    }

    private function normalizeFlow(string $flow): string
    {
        return in_array($flow, ['incomes', 'expenses', 'total'], true) ? $flow : 'total';
    }

    private function normalizeOverlay(string $overlay): string
    {
        return in_array($overlay, ['fact_only', 'fact_plus_expectations'], true)
            ? $overlay
            : 'fact_plus_expectations';
    }

    private function normalizeDirection(?string $direction): ?string
    {
        if ($direction === null || $direction === '' || $direction === 'all') {
            return null;
        }

        return $direction;
    }

    private function resolveAnchor(?string $anchor): CarbonImmutable
    {
        if ($anchor !== null && $anchor !== '') {
            try {
                return CarbonImmutable::parse($anchor)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBounds(string $period, CarbonImmutable $anchor): array
    {
        return match ($period) {
            'Q' => [$anchor->startOfQuarter(), $anchor->endOfQuarter()],
            'H' => $anchor->month <= 6
                ? [$anchor->startOfYear(), $anchor->startOfYear()->addMonths(5)->endOfMonth()]
                : [$anchor->startOfYear()->addMonths(6), $anchor->endOfYear()],
            'Y' => [$anchor->startOfYear(), $anchor->endOfYear()],
            default => [$anchor->startOfMonth(), $anchor->endOfMonth()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(
        string $mode,
        bool $hasThinCash,
        string $period,
        string $flow,
        string $overlay,
        ?string $direction,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $emptyGauge = ['plan' => 0.0, 'fact' => 0.0, 'pct' => null, 'label' => 'Итого'];

        return [
            'mode' => $mode,
            'period' => $period,
            'period_options' => [
                ['value' => 'M', 'label' => 'Месяц'],
                ['value' => 'Q', 'label' => 'Квартал'],
                ['value' => 'H', 'label' => 'Полугодие'],
                ['value' => 'Y', 'label' => 'Год'],
            ],
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'flow' => $flow,
            'overlay' => $overlay,
            'direction' => $direction,
            'direction_options' => [['value' => null, 'label' => 'Все направления']],
            'gauges' => [
                'incomes' => [...$emptyGauge, 'label' => 'Поступления'],
                'expenses' => [...$emptyGauge, 'label' => 'Списания'],
                'total' => $emptyGauge,
            ],
            'active_gauge' => $emptyGauge,
            'accuracy' => [],
            'by_direction' => [],
            'lenses' => [
                'cash_now' => [
                    'key' => 'cash_now',
                    'label' => 'Сейчас',
                    'subtitle' => 'Только касса / банк (не ДЗ/КЗ)',
                    'available' => $hasThinCash,
                    'value' => null,
                ],
                'expectations' => [
                    'key' => 'expectations',
                    'label' => 'Факт + ожидания (ДЗ/КЗ)',
                    'subtitle' => 'Оверлей неоплаченных строк графика',
                    'available' => true,
                    'value' => [
                        'expected_in' => 0.0,
                        'expected_out' => 0.0,
                        'receivables' => 0.0,
                        'payables' => 0.0,
                    ],
                ],
                'management_pnl' => [
                    'key' => 'management_pnl',
                    'label' => 'Упр. ОПиУ',
                    'subtitle' => 'Начисление по заказам — отдельно, не касса',
                    'available' => false,
                    'stub' => true,
                    'period' => $period,
                    'message' => 'Управленческий ОПиУ (M/Q/H/Y) — P2. Здесь только селектор периода для будущего отчёта; не путать с графиком оплат.',
                ],
            ],
            'projection' => null,
            'labels' => [
                'plan' => 'План (график)',
                'fact' => 'Факт (оплачено по графику)',
                'bank_fact_deferred' => null,
            ],
        ];
    }
}
