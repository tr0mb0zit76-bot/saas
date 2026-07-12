<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Saas\TenantUsageMeter;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SaasRecordUsageCommand extends Command
{
    protected $signature = 'saas:record-usage';

    protected $description = 'Record daily usage metrics per tenant (users, orders/month, storage)';

    public function handle(TenantUsageMeter $meter): int
    {
        TenantContext::bypass(true);

        $count = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($meter, &$count): void {
            $meter->recordFor($tenant);
            $count++;
        });

        TenantContext::bypass(false);

        $this->info("Recorded usage for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
