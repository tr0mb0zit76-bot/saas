<?php

namespace App\Http\Controllers;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Services\Finance\CashFlowControlDashboardService;
use App\Services\Finance\FinanceOverviewService;
use App\Services\ManagementAccounting\ManagementBankAccountSyncService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleTableColumns;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceIndexController extends Controller
{
    public function __invoke(
        Request $request,
        FinanceOverviewService $financeOverview,
        CashFlowControlDashboardService $cashFlowDashboard,
    ): Response|RedirectResponse {
        if ($request->query('section') === 'documents') {
            return redirect()->route('finance.index');
        }

        $user = $request->user();

        $activeSubmodule = match ($request->query('section')) {
            'dds', 'cashflow' => 'cashflow',
            default => 'overview',
        };

        $tenant = TenantContext::get();
        $paymentSchedulesFeature = (bool) ($tenant?->featureEnabled('payment_schedules'));
        $canViewSchedules = RoleAccess::canViewPaymentSchedules($user) && $paymentSchedulesFeature;

        if ($activeSubmodule === 'cashflow') {
            if (! RoleAccess::canViewPaymentSchedules($user)) {
                return redirect()->route('finance.index');
            }
            // ADR-025 P0: Start without payment_schedules must not open cashflow dashboard.
            abort_unless($paymentSchedulesFeature, 403);
        }

        PaymentScheduleAutomaticStatus::refreshForUser($user);

        $cashFlow = $canViewSchedules
            ? $financeOverview->cashFlowJournal($user)
            : collect();
        $cashFlowStats = $canViewSchedules
            ? $financeOverview->cashFlowStats($user)
            : $financeOverview->defaultCashFlowStats();

        $cashflowTab = (string) $request->query('cashflow_tab', 'schedule');
        if (! in_array($cashflowTab, ['schedule', 'reconcile'], true)) {
            $cashflowTab = 'schedule';
        }

        if ($cashflowTab === 'reconcile' && ! RoleAccess::canAccessPaymentReconcile($user)) {
            $cashflowTab = 'schedule';
        }

        $payload = [
            'summary' => [
                'cash_flow_total' => $cashFlow->count(),
                'cash_flow_pending' => $cashFlow->where('status', 'pending')->count(),
            ],
            'cashFlowJournal' => $cashFlow->values(),
            'active_submodule' => $activeSubmodule,
            'cashflow_tab' => $cashflowTab,
            'todays_cash_flow' => $cashFlowStats['periods']['today'],
            'cash_flow_stats' => $cashFlowStats,
            'can_access_salary_module' => RoleAccess::canAccessFinanceSalary($user),
            'can_access_management_accounting' => RoleAccess::canAccessManagementAccounting($user)
                && (bool) ($tenant?->featureEnabled('management_accounting')),
            'can_access_payment_reconcile' => RoleAccess::canAccessPaymentReconcile($user)
                && $paymentSchedulesFeature,
            'can_access_payment_schedules' => $canViewSchedules,
            'can_manage_payment_schedule' => RoleAccess::canManagePaymentSchedules($user) && $paymentSchedulesFeature,
            'can_show_payment_schedule_actions' => RoleAccess::canShowPaymentScheduleActionsColumn($user) && $paymentSchedulesFeature,
            'can_payment_schedule_record_payment' => RoleAccess::canRecordPaymentOnPaymentSchedule($user) && $paymentSchedulesFeature,
            'can_payment_schedule_cancel_row' => RoleAccess::canCancelPaymentScheduleRow($user) && $paymentSchedulesFeature,
            'paymentScheduleColumns' => PaymentScheduleTableColumns::options(),
        ];

        if ($activeSubmodule === 'cashflow' && $cashflowTab === 'schedule') {
            $payload['cashflow_dashboard'] = $cashFlowDashboard->build($user, [
                'period' => (string) $request->query('cf_period', 'M'),
                'flow' => (string) $request->query('cf_flow', 'total'),
                'overlay' => (string) $request->query('cf_overlay', 'fact_plus_expectations'),
                'direction' => $request->query('cf_direction'),
                'anchor' => $request->query('cf_anchor'),
            ]);
        }

        if ($activeSubmodule === 'cashflow' && $cashflowTab === 'reconcile') {
            $payload = [...$payload, ...$this->statementReconcileProps($request)];
        }

        return Inertia::render('Finance/Index', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function statementReconcileProps(Request $request): array
    {
        app(ManagementBankAccountSyncService::class)->syncFromOwnCompanies();

        $imports = ManagementStatementImport::query()
            ->with(['bankAccount:id,bank_name,account_mask,currency', 'importer:id,name'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(static fn (ManagementStatementImport $import): array => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'status' => $import->status,
                'period_from' => $import->period_from?->toDateString(),
                'period_to' => $import->period_to?->toDateString(),
                'lines_count' => $import->lines_count,
                'lines_allocated' => $import->lines_allocated,
                'total_in' => (float) $import->total_in,
                'total_out' => (float) $import->total_out,
                'bank_account' => $import->bankAccount === null ? null : [
                    'id' => $import->bankAccount->id,
                    'bank_name' => $import->bankAccount->bank_name,
                    'account_mask' => $import->bankAccount->account_mask,
                    'currency' => $import->bankAccount->currency,
                ],
                'importer_name' => $import->importer?->name,
                'created_at' => $import->created_at?->toIso8601String(),
                'pending_lines' => max(0, (int) $import->lines_count - (int) $import->lines_allocated),
                'has_allocated_lines' => (int) $import->lines_allocated > 0,
            ]);

        $bankAccounts = ManagementBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'bank_name', 'account_mask', 'currency']);

        return [
            'statement_imports' => $imports,
            'bank_accounts' => $bankAccounts,
            'default_bank_account_id' => $bankAccounts->first()?->id,
        ];
    }
}
