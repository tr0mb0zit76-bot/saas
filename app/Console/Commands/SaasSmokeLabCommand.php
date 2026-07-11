<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadConversionService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SaasSmokeLabCommand extends Command
{
    protected $signature = 'saas:smoke-lab';

    protected $description = 'Run automated SaaS lab smoke checks (M4)';

    public function handle(LeadConversionService $leadConversionService): int
    {
        $this->info('=== SaaS Lab Smoke ===');

        $tenant = Tenant::query()->where('slug', 'demo')->first();
        if ($tenant === null) {
            $this->error('Tenant demo not found. Run migrations first.');

            return self::FAILURE;
        }

        TenantContext::set($tenant);

        $manager = User::query()->where('email', 'manager@saas.local')->first();
        if ($manager === null) {
            $this->error('Demo manager not found. Run SaasDemoSeeder.');

            return self::FAILURE;
        }

        $contractors = Contractor::query()->count();
        $this->line("Contractors: {$contractors}");
        if ($contractors < 2) {
            $this->error('Expected at least 2 contractors.');

            return self::FAILURE;
        }

        $lead = Lead::query()->first();
        if ($lead === null) {
            $this->error('No leads found.');

            return self::FAILURE;
        }

        $customer = Contractor::query()
            ->where('type', 'customer')
            ->where('is_own_company', false)
            ->first();

        if ($customer !== null && $lead->counterparty_id === null) {
            $lead->forceFill(['counterparty_id' => $customer->id])->save();
        }

        $order = $leadConversionService->convert($lead->fresh(), $manager);
        $this->line("Lead #{$lead->id} → Order #{$order->id} ({$order->order_number})");

        $orders = Order::query()->count();
        if ($orders < 1) {
            $this->error('Order conversion failed.');

            return self::FAILURE;
        }

        $this->info('Smoke OK');

        return self::SUCCESS;
    }
}
