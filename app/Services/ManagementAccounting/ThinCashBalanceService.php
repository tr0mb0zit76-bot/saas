<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-023 P0 thin cash: statement-anchored + manual other + «сколько сейчас».
 * Not payment-schedule DSO/DPO; not margin−OPEX.
 */
class ThinCashBalanceService
{
    /**
     * @return array{
     *     label: string,
     *     disclaimer: string,
     *     as_of: string,
     *     opening_balance: float,
     *     opening_source: string,
     *     opening_as_of: string|null,
     *     turnover_in: float,
     *     turnover_out: float,
     *     turnover_net: float,
     *     bank_in: float,
     *     bank_out: float,
     *     manual_in: float,
     *     manual_out: float,
     *     balance_now: float,
     *     accounts: list<array{
     *         id: int,
     *         bank_name: string,
     *         account_mask: string|null,
     *         opening_balance: float|null,
     *         opening_balance_as_of: string|null,
     *         opening_balance_source: string|null,
     *         balance_now: float
     *     }>
     * }
     */
    public function build(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now()->startOfDay();

        if (! Schema::hasTable('management_statement_lines')
            || ! Schema::hasTable('management_bank_accounts')) {
            return $this->emptyPayload($asOf);
        }

        $accounts = ManagementBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $bankIn = 0.0;
        $bankOut = 0.0;
        $manualIn = 0.0;
        $manualOut = 0.0;
        $openingTotal = 0.0;
        $openingSources = [];
        $accountRows = [];

        foreach ($accounts as $account) {
            $opening = $this->resolveOpening($account);
            $openingTotal += $opening['amount'];
            $openingSources[] = $opening['source'];

            $turnovers = $this->turnoversForAccount($account, $opening['as_of'], $asOf);
            $bankIn += $turnovers['bank_in'];
            $bankOut += $turnovers['bank_out'];
            $manualIn += $turnovers['manual_in'];
            $manualOut += $turnovers['manual_out'];

            $accountNet = ($turnovers['bank_in'] + $turnovers['manual_in'])
                - ($turnovers['bank_out'] + $turnovers['manual_out']);

            $accountRows[] = [
                'id' => (int) $account->id,
                'bank_name' => (string) $account->bank_name,
                'account_mask' => $account->account_mask,
                'opening_balance' => $account->opening_balance !== null
                    ? (float) $account->opening_balance
                    : null,
                'opening_balance_as_of' => $account->opening_balance_as_of?->toDateString(),
                'opening_balance_source' => $account->opening_balance_source,
                'balance_now' => round($opening['amount'] + $accountNet, 2),
            ];
        }

        // Lines without bank_account still counted via accounts loop; orphan lines:
        $orphan = $this->orphanTurnovers($asOf);
        $bankIn += $orphan['bank_in'];
        $bankOut += $orphan['bank_out'];
        $manualIn += $orphan['manual_in'];
        $manualOut += $orphan['manual_out'];

        $turnoverIn = $bankIn + $manualIn;
        $turnoverOut = $bankOut + $manualOut;
        $balanceNow = $openingTotal + $turnoverIn - $turnoverOut;

        $uniqueSources = array_values(array_unique(array_filter($openingSources)));
        $openingSource = match (true) {
            $uniqueSources === ['manual'] => 'manual',
            $uniqueSources === ['statement'] => 'statement',
            count($uniqueSources) > 1 => 'mixed',
            default => 'none',
        };

        $openingAsOfDates = $accounts
            ->pluck('opening_balance_as_of')
            ->filter()
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->values();

        return [
            'label' => 'Остаток по выписке / ручному вводу',
            'disclaimer' => 'Тонкая касса пилота: безнал из выписки + ручные строки. Не полный ДДС и не план маржи−OPEX.',
            'as_of' => $asOf->toDateString(),
            'opening_balance' => round($openingTotal, 2),
            'opening_source' => $openingSource,
            'opening_as_of' => $openingAsOfDates->count() === 1 ? $openingAsOfDates->first() : null,
            'turnover_in' => round($turnoverIn, 2),
            'turnover_out' => round($turnoverOut, 2),
            'turnover_net' => round($turnoverIn - $turnoverOut, 2),
            'bank_in' => round($bankIn, 2),
            'bank_out' => round($bankOut, 2),
            'manual_in' => round($manualIn, 2),
            'manual_out' => round($manualOut, 2),
            'balance_now' => round($balanceNow, 2),
            'accounts' => $accountRows,
        ];
    }

    /**
     * @return array{amount: float, source: string, as_of: CarbonImmutable|null}
     */
    private function resolveOpening(ManagementBankAccount $account): array
    {
        if ($account->opening_balance === null) {
            return ['amount' => 0.0, 'source' => 'none', 'as_of' => null];
        }

        $source = in_array($account->opening_balance_source, ['manual', 'statement'], true)
            ? $account->opening_balance_source
            : 'manual';

        return [
            'amount' => (float) $account->opening_balance,
            'source' => $source,
            'as_of' => $account->opening_balance_as_of
                ? CarbonImmutable::parse($account->opening_balance_as_of)->startOfDay()
                : null,
        ];
    }

    /**
     * @return array{bank_in: float, bank_out: float, manual_in: float, manual_out: float}
     */
    private function turnoversForAccount(
        ManagementBankAccount $account,
        ?CarbonImmutable $fromExclusive,
        CarbonImmutable $asOf,
    ): array {
        $query = ManagementStatementLine::query()
            ->where('bank_account_id', $account->id)
            ->whereDate('operation_date', '<=', $asOf->toDateString());

        if ($fromExclusive !== null) {
            $query->whereDate('operation_date', '>', $fromExclusive->toDateString());
        }

        return $this->sumTurnovers($query->get(['direction', 'amount', 'source']));
    }

    /**
     * @return array{bank_in: float, bank_out: float, manual_in: float, manual_out: float}
     */
    private function orphanTurnovers(CarbonImmutable $asOf): array
    {
        $lines = ManagementStatementLine::query()
            ->whereNull('bank_account_id')
            ->whereDate('operation_date', '<=', $asOf->toDateString())
            ->get(['direction', 'amount', 'source']);

        return $this->sumTurnovers($lines);
    }

    /**
     * @param  iterable<int, ManagementStatementLine>  $lines
     * @return array{bank_in: float, bank_out: float, manual_in: float, manual_out: float}
     */
    private function sumTurnovers(iterable $lines): array
    {
        $bankIn = 0.0;
        $bankOut = 0.0;
        $manualIn = 0.0;
        $manualOut = 0.0;

        foreach ($lines as $line) {
            $amount = (float) $line->amount;
            $isManual = ($line->source ?? 'import') === 'manual';

            if ($line->direction === 'in') {
                if ($isManual) {
                    $manualIn += $amount;
                } else {
                    $bankIn += $amount;
                }
            } elseif ($line->direction === 'out') {
                if ($isManual) {
                    $manualOut += $amount;
                } else {
                    $bankOut += $amount;
                }
            }
        }

        return [
            'bank_in' => $bankIn,
            'bank_out' => $bankOut,
            'manual_in' => $manualIn,
            'manual_out' => $manualOut,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(CarbonImmutable $asOf): array
    {
        return [
            'label' => 'Остаток по выписке / ручному вводу',
            'disclaimer' => 'Тонкая касса пилота: безнал из выписки + ручные строки. Не полный ДДС и не план маржи−OPEX.',
            'as_of' => $asOf->toDateString(),
            'opening_balance' => 0.0,
            'opening_source' => 'none',
            'opening_as_of' => null,
            'turnover_in' => 0.0,
            'turnover_out' => 0.0,
            'turnover_net' => 0.0,
            'bank_in' => 0.0,
            'bank_out' => 0.0,
            'manual_in' => 0.0,
            'manual_out' => 0.0,
            'balance_now' => 0.0,
            'accounts' => [],
        ];
    }
}
