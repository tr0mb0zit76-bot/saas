<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\User;
use App\Services\ManagementAccounting\ThinCashBalanceService;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\RoleAccess;
use App\Support\ScopedPaymentScheduleQuery;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-025 P0: «Баланс» = WC / liquidity lite narrative (not 1С double-entry BS).
 */
class WorkingCapitalBalanceService
{
    public function __construct(
        private readonly ThinCashBalanceService $thinCash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        $tenant = TenantContext::get();
        $canThinCash = RoleAccess::canAccessManagementAccounting($user)
            && (bool) ($tenant?->featureEnabled('management_accounting'));

        $cashNow = null;
        if ($canThinCash) {
            $thin = $this->thinCash->build();
            $cashNow = [
                'balance_now' => (float) $thin['balance_now'],
                'label' => (string) $thin['label'],
                'disclaimer' => (string) $thin['disclaimer'],
            ];
        }

        [$ar, $ap] = $this->arApTotals($user);
        $cash = $cashNow['balance_now'] ?? null;

        $ownMoney = round(($cash ?? 0) + $ar, 2);
        $borrowed = round($ap, 2);

        $cashToAp = $ap > 0.009 && $cash !== null
            ? round($cash / $ap, 2)
            : null;
        $quickRatio = $ap > 0.009
            ? round(((($cash ?? 0) + $ar) / $ap), 2)
            : null;

        return [
            'report_kind' => 'working_capital_lite',
            'title' => 'Баланс',
            'subtitle' => 'Управленческий срез оборотного капитала',
            'disclaimer' => 'Это не бухгалтерский баланс 1С и не двойная запись. Только касса (если доступна) + ДЗ/КЗ из графика оплат.',
            'pnl_recognition_basis' => $tenant?->pnlRecognitionBasis() ?? 'execution_or_closed',
            'snapshot' => [
                'cash' => $cash,
                'cash_available' => $canThinCash,
                'receivables' => $ar,
                'payables' => $ap,
                'own_money_estimate' => $ownMoney,
                'borrowed_estimate' => $borrowed,
            ],
            'labels' => [
                'cash' => 'На счетах (касса)',
                'receivables' => 'В дебиторке (заказчики)',
                'payables' => 'К оплате (перевозчики)',
                'own_money' => 'Свои деньги (оценка)',
                'borrowed' => 'Чужие (долги)',
                'liquidity' => 'Ликвидность (упр.)',
            ],
            'liquidity' => [
                'cash_to_payables' => $cashToAp,
                'cash_plus_ar_to_payables' => $quickRatio,
                'note' => 'Управленческие коэффициенты из графика/кассы, не коэффициент из бух. баланса.',
            ],
            'cash_now' => $cashNow,
            'working_capital_url' => '/finance/working-capital',
            'cashflow_url' => '/finance?section=cashflow',
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function arApTotals(?User $user): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('orders')) {
            return [0.0, 0.0];
        }

        $query = ScopedPaymentScheduleQuery::base($user);
        PaymentScheduleSettlementStatus::applyUnsettledRootScope($query);
        $outstanding = PaymentScheduleSettlementStatus::outstandingAmountSql();

        $rows = $query
            ->selectRaw(
                'LOWER(TRIM(payment_schedules.party)) as party, SUM('.$outstanding.') as amount'
            )
            ->groupBy(DB::raw('LOWER(TRIM(payment_schedules.party))'))
            ->get()
            ->keyBy('party');

        $ar = round((float) (optional($rows->get('customer'))->amount ?? 0), 2);
        $ap = round(
            (float) (optional($rows->get('carrier'))->amount ?? 0)
            + (float) (optional($rows->get('contractor'))->amount ?? 0),
            2
        );

        return [$ar, $ap];
    }
}
