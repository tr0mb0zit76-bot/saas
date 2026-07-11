<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class TenantBillingService
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
    ) {}

    public function markInvoicePaid(Tenant $tenant, ?float $amount = null, ?string $notes = null): TenantSubscription
    {
        $subscription = $this->ensureSubscription($tenant);
        $periodStart = $this->resolveNextPeriodStart($subscription);
        $periodEnd = $periodStart->copy()->addMonth()->subDay();

        $invoiceNumber = $this->nextInvoiceNumber($tenant);

        TenantInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount ?? 0,
            'currency' => 'RUB',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => $notes,
        ]);

        $subscription->update([
            'status' => 'active',
            'plan' => $tenant->planKey(),
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'suspended_at' => null,
        ]);

        $tenant->update([
            'status' => 'active',
        ]);

        return $subscription->fresh();
    }

    public function expireTrials(Carbon $asOf): int
    {
        $expired = 0;

        TenantSubscription::query()
            ->with('tenant')
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $asOf)
            ->each(function (TenantSubscription $subscription) use (&$expired): void {
                $tenant = $subscription->tenant;

                if ($tenant === null) {
                    return;
                }

                $subscription->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);

                $tenant->update(['status' => 'suspended']);
                $expired++;
            });

        return $expired;
    }

    private function ensureSubscription(Tenant $tenant): TenantSubscription
    {
        $subscription = $tenant->subscription;

        if ($subscription !== null) {
            return $subscription;
        }

        return $this->provisioner->syncSubscription($tenant);
    }

    private function resolveNextPeriodStart(TenantSubscription $subscription): Carbon
    {
        if ($subscription->billing_period_end !== null) {
            $candidate = $subscription->billing_period_end->copy()->addDay();

            if ($candidate->isFuture() || $candidate->isToday()) {
                return $candidate;
            }
        }

        return now()->startOfDay();
    }

    private function nextInvoiceNumber(Tenant $tenant): string
    {
        $prefix = strtoupper(Str::slug($tenant->slug, ''));
        $sequence = TenantInvoice::query()->where('tenant_id', $tenant->id)->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, now()->format('Ym'), $sequence);
    }
}
