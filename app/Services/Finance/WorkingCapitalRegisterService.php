<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\User;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\ScopedPaymentScheduleQuery;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-025: AR/AP register by counterparty from unpaid payment_schedules.
 */
class WorkingCapitalRegisterService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('orders')) {
            return $this->emptyPayload();
        }

        $query = ScopedPaymentScheduleQuery::base($user);
        PaymentScheduleSettlementStatus::applyUnsettledRootScope($query);

        $outstanding = PaymentScheduleSettlementStatus::outstandingAmountSql();
        $counterpartySql = ScopedPaymentScheduleQuery::counterpartyIdSql();

        $rows = $query
            ->selectRaw("{$counterpartySql} as contractor_id")
            ->selectRaw('LOWER(TRIM(payment_schedules.party)) as party')
            ->selectRaw("SUM({$outstanding}) as amount")
            ->selectRaw('COUNT(*) as lines_count')
            ->groupBy(DB::raw($counterpartySql), DB::raw('LOWER(TRIM(payment_schedules.party))'))
            ->get();

        $contractorIds = $rows->pluck('contractor_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $names = $this->contractorNames($contractorIds);

        $receivables = [];
        $payables = [];
        $arTotal = 0.0;
        $apTotal = 0.0;

        foreach ($rows as $row) {
            $amount = round((float) ($row->amount ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }

            $party = strtolower(trim((string) ($row->party ?? '')));
            $contractorId = (int) ($row->contractor_id ?? 0);
            $entry = [
                'contractor_id' => $contractorId > 0 ? $contractorId : null,
                'contractor_name' => $contractorId > 0
                    ? ($names[$contractorId] ?? ('Контрагент #'.$contractorId))
                    : 'Без контрагента',
                'amount' => $amount,
                'lines_count' => (int) ($row->lines_count ?? 0),
                'side' => $party === 'customer' ? 'receivable' : 'payable',
            ];

            if ($party === 'customer') {
                $receivables[] = $entry;
                $arTotal += $amount;
            } else {
                $payables[] = $entry;
                $apTotal += $amount;
            }
        }

        usort($receivables, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        usort($payables, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return [
            'report_kind' => 'working_capital_register',
            'title' => 'Оборотный капитал',
            'subtitle' => 'Дебиторка заказчиков и кредиторка перевозчиков по неоплаченным строкам графика',
            'disclaimer' => 'Не начисление и не бухгалтерский баланс. Источник — payment_schedules (остаток).',
            'totals' => [
                'receivables' => round($arTotal, 2),
                'payables' => round($apTotal, 2),
                'net' => round($arTotal - $apTotal, 2),
            ],
            'receivables' => $receivables,
            'payables' => $payables,
            'cashflow_url' => '/finance?section=cashflow',
            'balance_url' => '/finance/balance',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'report_kind' => 'working_capital_register',
            'title' => 'Оборотный капитал',
            'subtitle' => 'Дебиторка заказчиков и кредиторка перевозчиков по неоплаченным строкам графика',
            'disclaimer' => 'Не начисление и не бухгалтерский баланс. Источник — payment_schedules (остаток).',
            'totals' => [
                'receivables' => 0.0,
                'payables' => 0.0,
                'net' => 0.0,
            ],
            'receivables' => [],
            'payables' => [],
            'cashflow_url' => '/finance?section=cashflow',
            'balance_url' => '/finance/balance',
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function contractorNames(array $ids): array
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
}
