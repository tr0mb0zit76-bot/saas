<?php

namespace Tests\Feature\Saas;

use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\TenantSubscription;
use App\Services\Saas\TenantBillingService;
use App\Services\Saas\TenantProvisioner;
use App\Support\TenantContext;
use Carbon\Carbon;
use Tests\SaasTestCase;

class TenantBillingTest extends SaasTestCase
{
    public function test_mark_invoice_paid_extends_billing_period(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'bill-'.uniqid(),
            'name' => 'Billing Test',
            'status' => 'trial',
            'plan' => 'pro',
        ]);

        app(TenantProvisioner::class)->provision($tenant);

        TenantContext::bypass(false);

        app(TenantBillingService::class)->markInvoicePaid($tenant->fresh());

        TenantContext::bypass(true);

        $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('active', $subscription?->status);
        $this->assertNotNull($subscription?->billing_period_end);
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertSame(1, TenantInvoice::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->count());

        TenantContext::bypass(false);
    }

    public function test_expire_trials_suspends_tenant(): void
    {
        TenantContext::bypass(true);

        $tenant = Tenant::query()->create([
            'slug' => 'trial-exp-'.uniqid(),
            'name' => 'Trial Expire',
            'status' => 'trial',
            'plan' => 'start',
            'trial_ends_at' => now()->subDay(),
        ]);

        app(TenantProvisioner::class)->syncSubscription($tenant);

        TenantContext::bypass(false);

        $count = app(TenantBillingService::class)->expireTrials(Carbon::now());

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertSame('suspended', $tenant->fresh()->status);
    }
}
